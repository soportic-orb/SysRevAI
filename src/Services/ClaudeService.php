<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Anthropic Claude API client.
 *
 * Phase 2 ships only the connection check used by the admin panel; the full
 * feature methods (summaries, screening suggestions, extraction, bias
 * assessment, article chat, semantic dedup) land in Phase 7.
 *
 * Uses native cURL so it works regardless of whether Guzzle is installed yet.
 */
final class ClaudeService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-4-7',
    ) {
    }

    public static function fromSettings(): self
    {
        return new self(
            (string) (setting('claude.api_key') ?? ''),
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7'),
        );
    }

    /**
     * Minimal 1-token request to confirm the key/model work.
     * @return array{ok:bool,message:string}
     */
    public function verifyConnection(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => 'No API key configured.'];
        }

        $payload = [
            'model'      => $this->model,
            'max_tokens' => 1,
            'messages'   => [['role' => 'user', 'content' => 'ping']],
        ];

        [$status, $body, $error] = $this->post($payload);

        if ($error !== null) {
            return ['ok' => false, 'message' => $error];
        }
        if ($status === 200) {
            return ['ok' => true, 'message' => 'Connection OK (model: ' . $this->model . ').'];
        }

        $decoded = json_decode($body, true);
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        return ['ok' => false, 'message' => (string) $msg];
    }

    /** @return array{0:int,1:string,2:?string} [httpStatus, body, transportError] */
    private function post(array $payload): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [$status, is_string($body) ? $body : '', $err];
    }
}
