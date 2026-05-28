<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\ReferenceFullTextStatus;
use SysRevAI\Services\FileStorage;
use SysRevAI\Services\PdfService;

/**
 * Downloads the PDF and/or JATS XML referenced by a FullTextResult, stores
 * them under storage/ with the existing FileStorage helpers, extracts the
 * text body and persists it in `reference_full_text` so Claude chat, AI
 * summaries and search treat retrieved content exactly like a manual upload.
 *
 * All network I/O is best-effort: failures degrade silently and never break
 * the calling orchestrator.
 */
final class FullTextDownloader
{
    /** Hard ceiling for any single download (mirrored from admin Files settings). */
    private static function maxBytes(): int
    {
        return max(1, (int) (setting('files.max_pdf_mb') ?? 50)) * 1024 * 1024;
    }

    /**
     * Try to materialise the FullTextResult to disk + DB.
     *
     * @return array{pdf_path:?string,xml_path:?string,text_chars:int}
     */
    public static function process(int $referenceId, FullTextResult $result, ?int $userId): array
    {
        $maxBytes = self::maxBytes();
        $pdfPath  = null;
        $xmlPath  = null;
        $jats     = null;
        $pageCount = 0;

        // ---- PDF ----------------------------------------------------------
        if ($result->pdfUrl !== null && $result->pdfUrl !== '') {
            $dl = self::download($result->pdfUrl, $maxBytes);
            if ($dl['ok'] && self::looksLikePdf($dl['body'])) {
                $stored = FileStorage::storeBytes($dl['body'], 'pdf', FileStorage::PDF_DIR);
                if ($stored !== null) {
                    $pdfPath = $stored;
                    $extracted = PdfService::extract($stored);
                    $pageCount = (int) $extracted['pages'];
                    if (($extracted['text'] ?? '') !== '') {
                        $jats = ['plain_text' => (string) $extracted['text'], 'title' => '', 'abstract' => ''];
                    }
                }
            }
        }

        // ---- XML (JATS) ---------------------------------------------------
        $xmlUrl = (string) ($result->metadata['xml_url'] ?? '');
        if ($xmlUrl !== '') {
            $dl = self::download($xmlUrl, $maxBytes);
            if ($dl['ok'] && self::looksLikeXml($dl['body'])) {
                $parsed = JatsParser::parse($dl['body']);
                if ($parsed !== null && $parsed['plain_text'] !== '') {
                    $xmlPath = FileStorage::storeBytes($dl['body'], 'xml', 'xml');
                    // JATS text is usually richer than the PDF render — prefer it.
                    $jats = [
                        'plain_text' => $parsed['plain_text'],
                        'title'      => $parsed['title'],
                        'abstract'   => $parsed['abstract'],
                    ];
                    if ($pageCount === 0) {
                        $pageCount = $parsed['section_count'];
                    }
                }
            }
        }

        // ---- Persist ------------------------------------------------------
        $textChars = 0;
        if ($jats !== null) {
            try {
                ReferenceFullText::save(
                    $referenceId,
                    (string) ($pdfPath ?? ''),
                    self::filename($result),
                    (string) $jats['plain_text'],
                    $pageCount,
                    $pdfPath !== null ? (int) @filesize($pdfPath) : 0,
                    (int) ($userId ?? 0)
                );
                $textChars = mb_strlen((string) $jats['plain_text']);
            } catch (\Throwable) {
                // DB or schema unavailable (tests, pre-install) — ignore.
            }
        }

        if ($pdfPath !== null || $xmlPath !== null) {
            try {
                ReferenceFullTextStatus::markStored($referenceId, $pdfPath, $xmlPath);
            } catch (\Throwable) {
                // best effort
            }
        }

        return ['pdf_path' => $pdfPath, 'xml_path' => $xmlPath, 'text_chars' => $textChars];
    }

    /* ── HTTP / sniffing ───────────────────────────────────────────────── */

    /** @return array{ok:bool,status:int,content_type:string,body:string,error:?string} */
    private static function download(string $url, int $maxBytes): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'content_type' => '', 'body' => '', 'error' => 'curl_init'];
        }
        $buf = '';
        $aborted = false;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'SysRevAI/' . (string) config('app.version', '0.1.0-dev'),
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$buf, $maxBytes, &$aborted): int {
                $buf .= $chunk;
                if (strlen($buf) > $maxBytes) {
                    $aborted = true;
                    return 0; // aborts the transfer
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err    = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($aborted) {
            return ['ok' => false, 'status' => $status, 'content_type' => $type, 'body' => '', 'error' => 'too_large'];
        }
        if ($err !== null || $status !== 200 || $buf === '') {
            return ['ok' => false, 'status' => $status, 'content_type' => $type, 'body' => '', 'error' => $err ?? ('http_' . $status)];
        }
        return ['ok' => true, 'status' => $status, 'content_type' => $type, 'body' => $buf, 'error' => null];
    }

    public static function looksLikePdf(string $body): bool
    {
        return str_starts_with($body, '%PDF-');
    }

    public static function looksLikeXml(string $body): bool
    {
        $head = ltrim($body);
        return str_starts_with($head, '<?xml') || str_starts_with($head, '<');
    }

    private static function filename(FullTextResult $result): string
    {
        $base = $result->source;
        if (!empty($result->metadata['pmcid'])) {
            $base .= '-' . (string) $result->metadata['pmcid'];
        } elseif (!empty($result->metadata['arxiv_id'])) {
            $base .= '-' . (string) $result->metadata['arxiv_id'];
        }
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) . '.pdf';
    }
}
