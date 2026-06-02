<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * Plumbing shared by every external bibliographic-database adapter: a
 * polite cURL GET with retries on 429/503, the SysRevAI User-Agent, and
 * a guard against pathological response sizes. Mirrors the equivalent
 * helper in FullTextRetrieval but lives in its own namespace so the two
 * subsystems can evolve independently (different rate limits, different
 * timeouts, different result shapes).
 */
abstract class BaseHttpBiblioSource implements BiblioSearchSourceInterface
{
    protected const TIMEOUT_SECONDS = 8;
    protected const MAX_BODY_BYTES  = 4 * 1024 * 1024;

    /**
     * @return array{status:int,body:string,error:?string}
     */
    protected function get(string $url, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'User-Agent: ' . $this->userAgent(),
            'Accept: application/json',
        ], $extraHeaders);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => static::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => $this->userAgent(),
        ]);
        $body = (string) curl_exec($ch);
        $err  = curl_errno($ch) !== 0 ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (strlen($body) > static::MAX_BODY_BYTES) {
            $body = '';
            $err  = 'oversize_body';
        }
        return ['status' => $status, 'body' => $body, 'error' => $err];
    }

    private function userAgent(): string
    {
        $version = (string) config('app.version', '0.1.0-dev');
        $url     = (string) (setting('site.url') ?? config('app.url', ''));
        $email   = (string) (setting('fulltext.polite_email')
            ?? setting('crossref.email')
            ?? '');
        $bits = ['SysRevAI/' . $version];
        if ($url !== '') {
            $bits[] = '+' . $url;
        }
        if ($email !== '') {
            $bits[] = 'mailto:' . $email;
        }
        return $bits[0] . ' (' . implode('; ', array_slice($bits, 1)) . ')';
    }
}
