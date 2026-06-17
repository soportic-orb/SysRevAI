<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\Translation;

/**
 * Google Cloud Translation v3 client.
 *
 * Signs a JWT with the configured service-account private key, exchanges it
 * for an OAuth2 access token (cached in storage/cache), and translates text
 * in chunks. Results are persisted in the `translations` table so repeated
 * passages never hit the API twice.
 *
 * Uses native cURL + OpenSSL — no Google SDK required.
 */
final class TranslateService
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-translation';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const TOKEN_CACHE_FILE = 'google_oauth_token.json';
    private const CHUNK_CHARS = 4500;

    /** @return array{ok:bool,message:string} Configuration check used by admin. */
    public static function verifyConfig(): array
    {
        $projectId = (string) (setting('google.project_id') ?? '');
        $credPath  = (string) (setting('google.credentials_path') ?? '');

        if ($projectId === '') {
            return ['ok' => false, 'message' => 'Project ID is not set.'];
        }
        if ($credPath === '' || !is_file($credPath)) {
            return ['ok' => false, 'message' => 'Service-account JSON is not uploaded.'];
        }

        $sa = self::loadServiceAccount($credPath);
        if ($sa === null) {
            return ['ok' => false, 'message' => 'Service-account JSON is invalid.'];
        }

        $token = self::accessToken();
        if ($token === null) {
            return ['ok' => false, 'message' => 'Could not obtain an OAuth2 access token.'];
        }

        return ['ok' => true, 'message' => 'Configuration looks valid (project: ' . $projectId . ').'];
    }

    /**
     * Translate text into the target language. Empty/blank input is passed
     * through. Engine selection:
     *
     *   1. Cached translations always win (regardless of engine).
     *   2. Google Cloud Translation v3 when the admin has configured a
     *      project_id AND uploaded a service-account JSON.
     *   3. Otherwise — or if a Google call fails — fall back to
     *      Anthropic Claude, which the platform already requires.
     *
     * This is what makes the Translate button work out-of-the-box on a
     * fresh install: the user never sees a `no_token` error just
     * because Google credentials haven't been uploaded yet.
     *
     * Returns ['ok'=>bool,'text'=>?,'error'=>?].
     */
    public static function translate(string $text, string $target, string $source = 'auto'): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return ['ok' => true, 'text' => $text, 'error' => null];
        }

        $googleReady = self::googleConfigured();

        $chunks = self::splitIntoChunks($text);
        $out = [];
        foreach ($chunks as $chunk) {
            $cached = Translation::find($chunk, $source, $target);
            if ($cached !== null) {
                $out[] = $cached;
                continue;
            }

            $result = null;
            if ($googleReady) {
                $result = self::callApi($chunk, $target, $source);
            }
            if ($result === null || !$result['ok']) {
                // Either Google isn't configured at all, or the
                // request just failed (auth blip, quota, etc.). Fall
                // back to Claude so the user still gets a translation.
                $result = self::claudeFallback($chunk, $target, $source);
                if (!$result['ok']) {
                    return $result;
                }
            }
            $translated = (string) $result['text'];
            Translation::store($chunk, $source, $target, $translated);
            $out[] = $translated;
        }
        return ['ok' => true, 'text' => implode("\n\n", $out), 'error' => null];
    }

    /** True when both project_id + service-account JSON are in place. */
    private static function googleConfigured(): bool
    {
        $projectId = (string) (setting('google.project_id') ?? '');
        if ($projectId === '') {
            return false;
        }
        $credPath = (string) (setting('google.credentials_path') ?? '');
        return $credPath !== '' && is_file($credPath);
    }

    /**
     * Translate a single chunk via Claude. Same return shape as
     * callApi() so the orchestrator above doesn't have to branch.
     *
     * @return array{ok:bool,text:?string,error:?string}
     */
    private static function claudeFallback(string $text, string $target, string $source): array
    {
        try {
            return ClaudeService::fromSettings()->translateText($text, $target, $source);
        } catch (\Throwable $e) {
            return ['ok' => false, 'text' => null, 'error' => $e->getMessage()];
        }
    }

    /* ── HTTP + auth ───────────────────────────────────────────────────── */

    /** @return array{ok:bool,text:?string,error:?string} */
    private static function callApi(string $text, string $target, string $source): array
    {
        $projectId = (string) (setting('google.project_id') ?? '');
        if ($projectId === '') {
            return ['ok' => false, 'text' => null, 'error' => 'no_project'];
        }
        $token = self::accessToken();
        if ($token === null) {
            return ['ok' => false, 'text' => null, 'error' => 'no_token'];
        }

        $url = "https://translation.googleapis.com/v3/projects/{$projectId}/locations/global:translateText";
        $payload = [
            'contents'            => [$text],
            'target_language_code' => $target,
            'mime_type'           => 'text/plain',
        ];
        if ($source !== '' && $source !== 'auto') {
            $payload['source_language_code'] = $source;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($err !== null) {
            return ['ok' => false, 'text' => null, 'error' => $err];
        }
        $decoded = json_decode((string) $raw, true);
        if ($status !== 200 || !is_array($decoded)) {
            return ['ok' => false, 'text' => null, 'error' => (string) ($decoded['error']['message'] ?? ('HTTP ' . $status))];
        }
        $translated = $decoded['translations'][0]['translatedText'] ?? null;
        return ['ok' => $translated !== null, 'text' => is_string($translated) ? $translated : null, 'error' => null];
    }

    /** Obtain a Google OAuth2 access token. Cached on disk for ~50 minutes. */
    private static function accessToken(): ?string
    {
        $cacheFile = (string) config('paths.cache') . '/' . self::TOKEN_CACHE_FILE;
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
                return (string) $cached['token'];
            }
        }

        $sa = self::loadServiceAccount((string) (setting('google.credentials_path') ?? ''));
        if ($sa === null) {
            return null;
        }

        $jwt = self::signJwt($sa);
        if ($jwt === null) {
            return null;
        }

        $ch = curl_init(self::TOKEN_URI);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);
        $token = $decoded['access_token'] ?? null;
        $ttl = (int) ($decoded['expires_in'] ?? 3600);
        if (!is_string($token)) {
            return null;
        }
        @file_put_contents($cacheFile, json_encode([
            'token'      => $token,
            'expires_at' => time() + $ttl,
        ]));
        @chmod($cacheFile, 0600);
        return $token;
    }

    private static function loadServiceAccount(string $path): ?array
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)
            || ($decoded['type'] ?? '') !== 'service_account'
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])) {
            return null;
        }
        return $decoded;
    }

    private static function signJwt(array $sa): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $sa['token_uri'] ?? self::TOKEN_URI,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $signingInput = self::b64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.' . self::b64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $signature = '';
        if (!openssl_sign($signingInput, $signature, (string) $sa['private_key'], 'sha256WithRSAEncryption')) {
            return null;
        }
        return $signingInput . '.' . self::b64UrlEncode($signature);
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /* ── Chunking ──────────────────────────────────────────────────────── */

    /**
     * Split a long text into ~CHUNK_CHARS-sized pieces, preferring paragraph
     * breaks so the cache stays effective for re-translations.
     *
     * @return string[]
     */
    public static function splitIntoChunks(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_CHARS) {
            return [$text];
        }
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];

        $chunks = [];
        $buffer = '';
        foreach ($paragraphs as $p) {
            $candidate = $buffer === '' ? $p : ($buffer . "\n\n" . $p);
            if (mb_strlen($candidate) <= self::CHUNK_CHARS) {
                $buffer = $candidate;
                continue;
            }
            if ($buffer !== '') {
                $chunks[] = $buffer;
                $buffer = '';
            }
            // The paragraph itself may exceed the chunk size — hard-wrap it.
            if (mb_strlen($p) > self::CHUNK_CHARS) {
                foreach (str_split($p, self::CHUNK_CHARS) as $piece) {
                    $chunks[] = $piece;
                }
            } else {
                $buffer = $p;
            }
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }
        return $chunks;
    }
}
