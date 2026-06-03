<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\Review;

/**
 * EvidenceHunt is an AI-assisted PubMed discovery service. We use it as a
 * complementary search lane next to the reproducible PRISMA-style external
 * databases — it returns a Markdown answer summarised by an LLM and a list
 * of the PubMed documents the answer is grounded on.
 *
 * Authentication: ONE account-level API key, sent ONLY as the X-API-Key
 * header. No OAuth, no Bearer JWT (that one's for their first-party web
 * app). The key is stored encrypted under setting('evidencehunt.api_key')
 * and never appears in URLs, query strings, logs or this file.
 *
 *   POST https://evidencehunt.com/api/v1/query
 *     headers: X-API-Key, Content-Type: application/json,
 *              Accept: application/vnd.evidencehunt.chat-stream+json
 *     body:    {question, sources, userLanguage, chatElaborateMode,
 *               followUp, outputId}
 *     reply:   NDJSON stream — each line is one JSON object with a
 *              "type" field (all_docs / answer_part / output_done /
 *              error / failed). We buffer the whole stream server-side
 *              and produce a single payload for the UI.
 *
 * No transport / parsing failures escape this class: query() always
 * returns ['ok' => bool, 'error' => ?string, …] so the controller can
 * render a graceful "EvidenceHunt isn't available right now" message.
 */
final class EvidenceHuntService
{
    private const ENDPOINT  = 'https://evidencehunt.com/api/v1/query';
    private const ACCEPT    = 'application/vnd.evidencehunt.chat-stream+json';

    // EvidenceHunt rejects questions over 1000 tokens with HTTP 400. We
    // estimate 1 token ≈ 4 characters (good enough for Latin scripts;
    // safe on the conservative side for CJK) and cap at 950 tokens worth
    // so prompt-template overhead on their side never tips us over.
    private const MAX_TOKENS   = 950;
    private const CHARS_PER_TOKEN = 4;

    private const TIMEOUT_SECONDS = 60;
    private const MAX_BODY_BYTES  = 8 * 1024 * 1024;

    /**
     * @param array{
     *   elaborate?:bool,
     *   userLanguage?:string,
     *   followUp?:bool,
     *   outputId?:string,
     *   sources?:list<string>
     * } $opts
     *
     * @return array{
     *   ok:bool, error:?string,
     *   docs:list<array<string,mixed>>, answer:string,
     *   output_id:?string,
     *   credits_used:?int, credits_remaining:?int,
     *   status:?int
     * }
     */
    public function query(string $question, array $opts = []): array
    {
        $blank = [
            'ok' => false, 'error' => null,
            'docs' => [], 'answer' => '', 'output_id' => null,
            'credits_used' => null, 'credits_remaining' => null,
            'status' => null,
        ];

        $key = (string) (setting('evidencehunt.api_key') ?? '');
        if ($key === '') {
            $blank['error'] = 'missing_api_key';
            return $blank;
        }

        $question = trim($question);
        if ($question === '') {
            $blank['error'] = 'empty_question';
            return $blank;
        }
        $question = self::clipToTokens($question, self::MAX_TOKENS);

        $body = [
            'question'          => $question,
            'sources'           => $opts['sources']     ?? ['pubmed'],
            'userLanguage'      => self::normaliseLang((string) ($opts['userLanguage'] ?? 'en')),
            'chatElaborateMode' => (bool) ($opts['elaborate'] ?? false),
            'followUp'          => (bool) ($opts['followUp'] ?? false),
            'outputId'          => (string) ($opts['outputId'] ?? ''),
        ];

        // Capture response headers without including the request key — we
        // only care about X-Credits-* and we read them out of the headers
        // collected here, not from logs or anywhere else.
        $respHeaders = [];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::ENDPOINT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $key,
                'Content-Type: application/json',
                'Accept: ' . self::ACCEPT,
                'User-Agent: SysRevAI/' . (string) config('app.version', '0.1.0-dev'),
            ],
            CURLOPT_HEADERFUNCTION => static function ($_ch, string $line) use (&$respHeaders): int {
                $len = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $val] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($name))] = trim($val);
                }
                return $len;
            },
        ]);

        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $out = $blank;
        $out['status']            = $status;
        $out['credits_used']      = isset($respHeaders['x-credits-used'])      ? (int) $respHeaders['x-credits-used']      : null;
        $out['credits_remaining'] = isset($respHeaders['x-credits-remaining']) ? (int) $respHeaders['x-credits-remaining'] : null;

        if ($errno !== 0 || !is_string($raw)) {
            $out['error'] = 'network';
            return $out;
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            $out['error'] = 'oversize';
            return $out;
        }

        // Map the documented status codes to stable error tokens the
        // controller / view can translate. The body of 402 carries the
        // {error, required, balance} payload; the others ship plain text
        // we don't surface verbatim (no leaking provider phrasing).
        if ($status !== 200) {
            $out['error'] = match ($status) {
                400     => 'bad_request',
                401     => 'invalid_key',
                402     => 'no_credits',
                403     => 'no_source_access',
                429     => 'rate_limited',
                500,502,503,504 => 'server_error',
                default => 'http_' . $status,
            };
            return $out;
        }

        $parsed = self::parseNdjson($raw);
        $out['ok']        = true;
        $out['docs']      = $parsed['docs'];
        $out['answer']    = $parsed['answer'];
        $out['output_id'] = $parsed['output_id'];

        // Stream-level error / failed override the 200: EvidenceHunt
        // sometimes 200s with an in-stream error message instead of a
        // proper HTTP error.
        if ($parsed['error'] !== null) {
            $out['ok']    = false;
            $out['error'] = $parsed['error'];
        }
        return $out;
    }

    /**
     * Parse the NDJSON stream. Lines that are empty or invalid JSON are
     * silently skipped — partially delivered streams must not bring the
     * whole reply down with them.
     *
     * @return array{
     *   docs:list<array<string,mixed>>, answer:string,
     *   output_id:?string, error:?string
     * }
     */
    private static function parseNdjson(string $raw): array
    {
        $docs       = [];
        $parts      = [];
        $finalAns   = '';
        $outputId   = null;
        $error      = null;

        // Normalise CRLF / lone CR — different proxies break lines
        // differently.
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $obj = json_decode($line, true);
            if (!is_array($obj)) {
                continue;
            }
            $type = (string) ($obj['type'] ?? '');
            switch ($type) {
                case 'all_docs':
                    foreach ((array) ($obj['docs'] ?? []) as $d) {
                        if (is_array($d)) {
                            $docs[] = self::normaliseDoc($d);
                        }
                    }
                    break;
                case 'answer_part':
                    $idx = isset($obj['index']) && is_numeric($obj['index']) ? (int) $obj['index'] : count($parts);
                    $parts[$idx] = (string) ($obj['text'] ?? '');
                    break;
                case 'output_done':
                    $finalAns = (string) ($obj['answer'] ?? '');
                    $outputId = isset($obj['output_id']) ? (string) $obj['output_id'] : $outputId;
                    break;
                case 'error':
                    $error = $error ?? 'stream_error';
                    break;
                case 'failed':
                    $error = $error ?? 'stream_failed';
                    break;
            }
        }

        if ($finalAns === '' && $parts !== []) {
            ksort($parts);
            $finalAns = implode('', $parts);
        }

        return [
            'docs'      => $docs,
            'answer'    => $finalAns,
            'output_id' => $outputId,
            'error'     => $error,
        ];
    }

    /**
     * Normalise a PubMed-shaped EvidenceHunt document to the platform's
     * common reference row. `id` is the PMID; abstract / journal / DOI
     * fields are optional. URL falls back to the PubMed landing page if
     * we have a PMID, otherwise the DOI resolver.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    public static function normaliseDoc(array $d): array
    {
        $authors = [];
        foreach ((array) ($d['authors'] ?? []) as $a) {
            $name = trim((string) $a);
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $pmid = '';
        if (isset($d['id']) && (is_string($d['id']) || is_int($d['id']))) {
            $pmid = trim((string) $d['id']);
        }

        $doi = trim((string) ($d['doi'] ?? ''));
        $doi = (string) preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $doi);

        $url = '';
        if ($pmid !== '') {
            $url = 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/';
        } elseif ($doi !== '') {
            $url = 'https://doi.org/' . $doi;
        }

        $year = null;
        if (isset($d['year']) && is_numeric($d['year'])) {
            $year = (int) $d['year'];
        }

        return [
            'title'    => trim((string) ($d['title']    ?? '')),
            'authors'  => $authors,
            'year'     => $year,
            'journal'  => trim((string) ($d['journal']  ?? '')),
            'abstract' => trim((string) ($d['abstract'] ?? '')),
            'doi'      => $doi,
            'pmid'     => $pmid,
            'url'      => $url,
            'keywords' => [],
        ];
    }

    /**
     * Derive an EvidenceHunt-ready question from a review's protocol.
     *
     * Priority:
     *   1. The review's free-text research question (`reviews.question`) —
     *      it's the most authoritative articulation, written by the team
     *      and already curated, so we send it verbatim.
     *   2. A clinical-question sentence assembled from PICO, in the
     *      requested locale so the question reads natively (English /
     *      Spanish / Catalan; falls back to English for other codes).
     *   3. The review's title, as a last resort so we never POST an
     *      empty body.
     *
     * The PICO sentence is fully grammatical even when components are
     * missing — each clause is conditional and the trailing punctuation
     * is normalised.
     *
     * @param array<string,mixed> $review
     */
    public static function questionFromPico(array $review, ?string $locale = null): string
    {
        $researchQ = trim((string) ($review['question'] ?? ''));
        if ($researchQ !== '') {
            return $researchQ;
        }

        $pico = Review::pico($review);
        $pop  = self::trimClause((string) ($pico['population']   ?? ''));
        $itv  = self::trimClause((string) ($pico['intervention'] ?? ''));
        $cmp  = self::trimClause((string) ($pico['comparison']   ?? ''));
        $out  = self::trimClause((string) ($pico['outcome']      ?? ''));

        if ($pop === '' && $itv === '' && $out === '' && $cmp === '') {
            return trim((string) ($review['title'] ?? ''));
        }

        $lang = self::normaliseLang((string) ($locale ?? 'en'));
        return self::picoSentence($lang, $pop, $itv, $cmp, $out);
    }

    /**
     * Assemble a clinical question from PICO fragments in the requested
     * language. Each clause is conditional, so a review with only P and
     * I still produces a grammatical sentence.
     */
    private static function picoSentence(string $lang, string $pop, string $itv, string $cmp, string $out): string
    {
        $itv = $itv !== '' ? $itv : match ($lang) {
            'es' => 'la intervención',
            'ca' => 'la intervenció',
            default => 'the intervention',
        };

        switch ($lang) {
            case 'es':
                $parts = [];
                if ($pop !== '') {
                    $parts[] = 'En ' . $pop . ',';
                }
                $parts[] = '¿qué efecto tiene ' . $itv;
                if ($cmp !== '') {
                    $parts[] = 'en comparación con ' . $cmp;
                }
                if ($out !== '') {
                    $parts[] = 'sobre ' . $out;
                }
                return rtrim(implode(' ', $parts), ' ?.;,') . '?';

            case 'ca':
                $parts = [];
                if ($pop !== '') {
                    $parts[] = 'En ' . $pop . ',';
                }
                $parts[] = 'quin efecte té ' . $itv;
                if ($cmp !== '') {
                    $parts[] = 'comparat amb ' . $cmp;
                }
                if ($out !== '') {
                    $parts[] = 'sobre ' . $out;
                }
                return rtrim(implode(' ', $parts), ' ?.;,') . '?';

            default:
                $parts = [];
                if ($pop !== '') {
                    $parts[] = 'In ' . $pop . ',';
                }
                $parts[] = 'what is the effect of ' . $itv;
                if ($cmp !== '') {
                    $parts[] = 'compared with ' . $cmp;
                }
                if ($out !== '') {
                    $parts[] = 'on ' . $out;
                }
                return rtrim(implode(' ', $parts), ' ?.;,') . '?';
        }
    }

    /**
     * Normalise a PICO fragment: collapse whitespace, drop trailing
     * sentence terminators / dangling commas so they don't bleed into
     * the surrounding template.
     */
    private static function trimClause(string $s): string
    {
        $s = trim($s);
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        $s = rtrim($s, " \t\n\r\0\x0B.,;:?!");
        return $s;
    }

    /** Coerce arbitrary locale strings to a two-letter ISO 639-1 code. */
    private static function normaliseLang(string $locale): string
    {
        $code = strtolower(substr($locale, 0, 2));
        return $code !== '' ? $code : 'en';
    }

    /** Trim long inputs by character budget (≈ char/4 tokens). */
    private static function clipToTokens(string $text, int $maxTokens): string
    {
        $maxChars = max(1, $maxTokens * self::CHARS_PER_TOKEN);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars);
    }
}
