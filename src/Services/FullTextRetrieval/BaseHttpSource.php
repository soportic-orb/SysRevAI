<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Shared plumbing for full-text sources: native cURL GETs with a polite
 * User-Agent, exponential backoff on 429/503 responses, and helpers to read
 * configuration values from the encrypted settings.
 */
abstract class BaseHttpSource implements FullTextSourceInterface
{
    private const MAX_RETRIES = 3;
    private const BACKOFF_BASE_SECONDS = 1;

    /** Polite "mailto:" pool — owner email by default, overridable per source. */
    protected function politeEmail(): string
    {
        $sourceEmail = (string) (setting('fulltext.' . $this->name() . '.email') ?? '');
        if ($sourceEmail !== '') {
            return $sourceEmail;
        }
        return (string) (setting('fulltext.polite_email') ?? setting('crossref.email') ?? '');
    }

    /** Identifiable User-Agent — standard practice in CrossRef/Unpaywall pools. */
    protected function userAgent(): string
    {
        $version = (string) config('app.version', '0.1.0-dev');
        $url     = (string) (setting('site.url') ?? config('app.url', ''));
        $email   = $this->politeEmail();
        $bits = ['SysRevAI/' . $version];
        if ($url !== '') {
            $bits[] = '+' . $url;
        }
        if ($email !== '') {
            $bits[] = 'mailto:' . $email;
        }
        return $bits[0] . ' (' . implode('; ', array_slice($bits, 1)) . ')';
    }

    /**
     * GET a URL with retries on 429/503. Returns the response envelope
     * regardless of HTTP status — never throws on transport errors.
     *
     * @return array{status:int,body:string,headers:array<string,string>,error:?string,ms:int}
     */
    protected function get(string $url, array $headers = [], int $timeoutSec = 15): array
    {
        // Per-source request budget — checked before every call so a misbehaving
        // worker can never blow through an API's daily quota.
        if (!RateLimiter::acquire($this->name(), $this->rateLimit())) {
            return ['status' => 429, 'body' => '', 'headers' => [], 'error' => 'rate_limited', 'ms' => 0];
        }

        $headers = array_merge([
            'User-Agent: ' . $this->userAgent(),
            'Accept: application/json',
        ], $headers);

        $lastError = null;
        $status = 0;
        $body = '';
        $hdrs = [];
        $ms = 0;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $start = microtime(true);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_TIMEOUT        => $timeoutSec,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $err = curl_errno($ch) ? curl_error($ch) : null;
            curl_close($ch);
            $ms = (int) round((microtime(true) - $start) * 1000);

            if ($err !== null) {
                $lastError = $err;
            } else {
                $rawStr = is_string($raw) ? $raw : '';
                $hdrs = $this->parseHeaders(substr($rawStr, 0, $headerSize));
                $body = substr($rawStr, $headerSize);

                if ($status !== 429 && $status !== 503) {
                    return ['status' => $status, 'body' => $body, 'headers' => $hdrs, 'error' => null, 'ms' => $ms];
                }
                $lastError = 'http_' . $status;
            }

            if ($attempt < self::MAX_RETRIES - 1) {
                $sleep = self::BACKOFF_BASE_SECONDS * (2 ** $attempt);
                sleep(min($sleep, 30));
            }
        }

        return ['status' => $status, 'body' => $body, 'headers' => $hdrs, 'error' => $lastError, 'ms' => $ms];
    }

    /** GET and decode JSON; returns null on any failure. */
    protected function getJson(string $url, array $headers = [], int $timeoutSec = 15): ?array
    {
        $res = $this->get($url, $headers, $timeoutSec);
        if ($res['error'] !== null || $res['status'] !== 200 || $res['body'] === '') {
            return null;
        }
        $decoded = json_decode($res['body'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /** Normalize a DOI to its bare form (lowercase, strip URL prefixes). */
    protected function normalizeDoi(?string $doi): ?string
    {
        if ($doi === null) {
            return null;
        }
        $doi = strtolower(trim($doi));
        $doi = (string) preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        return $doi === '' ? null : $doi;
    }

    /** @return array<string,string> */
    private function parseHeaders(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $out[strtolower(trim($k))] = trim($v);
            }
        }
        return $out;
    }

    public function isEnabled(): bool
    {
        return (bool) (setting('fulltext.' . $this->name() . '.enabled') ?? false);
    }
}
