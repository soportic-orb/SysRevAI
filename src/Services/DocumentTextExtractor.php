<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Generic plain-text extraction from user-uploaded protocol documents.
 *
 * Supports PDF (via smalot/pdfparser, same path as full-text retrieval)
 * and Word .docx (read as a zip + stripped tags, no extra dependency
 * needed beyond the ext-zip / ext-dom already required by the platform).
 *
 * Returns an empty string when the format isn't recognised or extraction
 * fails — callers decide what to do (typically: surface a UI error).
 */
final class DocumentTextExtractor
{
    /** Hard cap on text we'll forward to the AI to keep costs predictable. */
    public const MAX_CHARS = 60_000;

    /**
     * @param string $path Absolute path to a readable file.
     * @param string $mime Reported MIME (optional — we fall back to extension).
     */
    public static function extract(string $path, string $mime = ''): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            $result = PdfService::extract($path);
            return self::truncate((string) ($result['text'] ?? ''));
        }
        if ($ext === 'docx'
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || $mime === 'application/zip'
        ) {
            return self::truncate(self::extractDocx($path));
        }
        // Legacy .doc is binary, skip gracefully.
        return '';
    }

    private static function extractDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return '';
        }

        // Preserve paragraph breaks before stripping XML tags.
        $xml = preg_replace('#<w:p\b[^/]*/>#i', "\n", $xml) ?? $xml;
        $xml = preg_replace('#</w:p>#i', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:br\b[^/]*/>#i', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Collapse runs of whitespace but keep line breaks intact.
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return $text;
        }
        return mb_substr($text, 0, self::MAX_CHARS);
    }
}
