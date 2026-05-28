<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\AiUsage;

/**
 * Anthropic Claude API client.
 *
 * Forces JSON responses for structured tasks, retries with exponential backoff,
 * and logs token usage/cost per review. Feature toggles and the monthly cost
 * limit (admin panel) gate every call.
 *
 * Uses native cURL so it works whether or not Guzzle is installed.
 */
final class ClaudeService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_RETRIES = 3;

    /** Estimated USD price per 1M tokens [input, output]. */
    private const PRICES = [
        'claude-opus-4-7'           => [15.0, 75.0],
        'claude-sonnet-4-6'         => [3.0, 15.0],
        'claude-haiku-4-5-20251001' => [1.0, 5.0],
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $modelComplex = 'claude-opus-4-7',
        private readonly string $modelLight = 'claude-haiku-4-5-20251001',
        private readonly int $maxTokens = 4096,
    ) {
    }

    public static function fromSettings(): self
    {
        return new self(
            (string) (setting('claude.api_key') ?? ''),
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7'),
            (string) (setting('claude.model_light') ?? 'claude-haiku-4-5-20251001'),
            (int) (setting('claude.max_tokens') ?? 4096),
        );
    }

    /* ── Public capabilities ───────────────────────────────────────────── */

    /** @return array{ok:bool,message:string} */
    public function verifyConnection(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => 'No API key configured.'];
        }
        $res = $this->request($this->modelComplex, 'Reply with "ok".',
            [['role' => 'user', 'content' => 'ping']], 1, 'verify', null);
        return $res['ok']
            ? ['ok' => true, 'message' => 'Connection OK (model: ' . $this->modelComplex . ').']
            : ['ok' => false, 'message' => $res['error'] ?? 'Unknown error'];
    }

    public function summarize(string $articleText, string $targetLanguage = 'ca', ?int $reviewId = null): array
    {
        if ($e = $this->guard('summaries')) {
            return $e;
        }
        $system = "You are a scientific summarization assistant. Summarize the article in the language "
            . "'{$targetLanguage}'. Respond ONLY with JSON: "
            . '{"background":"","methods":"","results":"","conclusions":"","relevance":""}.';
        $res = $this->request($this->modelComplex, $system,
            [['role' => 'user', 'content' => $this->truncate($articleText, 60000)]], $this->maxTokens, 'summaries', $reviewId, true);
        return $this->jsonResult($res);
    }

    public function suggestScreeningDecision(array $reference, array $protocol, ?int $reviewId = null): array
    {
        if ($e = $this->guard('screening')) {
            return $e;
        }
        $system = 'You assist with systematic-review title/abstract screening. Given the article and the '
            . 'protocol, recommend whether to include it. You advise only; the reviewer decides. '
            . 'Respond ONLY with JSON: {"recommendation":"include|exclude|maybe","confidence":0.0,"reason":""}.';
        $payload = json_encode([
            'article'  => [
                'title'    => $reference['title'] ?? '',
                'abstract' => $reference['abstract'] ?? '',
            ],
            'protocol' => $protocol,
        ], JSON_UNESCAPED_UNICODE);
        $res = $this->request($this->modelComplex, $system,
            [['role' => 'user', 'content' => $payload]], 1024, 'screening', $reviewId, true);
        return $this->jsonResult($res);
    }

    public function extractStructuredData(string $articleText, array $template, ?int $reviewId = null): array
    {
        if ($e = $this->guard('extraction')) {
            return $e;
        }
        $fields = json_encode($template, JSON_UNESCAPED_UNICODE);
        $system = "Extract the requested fields from the article. Respond ONLY with a JSON object whose keys "
            . "match this template (use null when not reported): {$fields}.";
        $res = $this->request($this->modelComplex, $system,
            [['role' => 'user', 'content' => $this->truncate($articleText, 80000)]], $this->maxTokens, 'extraction', $reviewId, true);
        return $this->jsonResult($res);
    }

    public function assessBiasDomain(string $articleText, string $tool, string $domain, ?int $reviewId = null): array
    {
        if ($e = $this->guard('bias')) {
            return $e;
        }
        $system = "You assess risk of bias using the {$tool} tool for the domain '{$domain}'. Advise only. "
            . 'Respond ONLY with JSON: {"judgement":"low|some_concerns|high|no_information","justification":""}.';
        $res = $this->request($this->modelComplex, $system,
            [['role' => 'user', 'content' => $this->truncate($articleText, 80000)]], 1024, 'bias', $reviewId, true);
        return $this->jsonResult($res);
    }

    /** @param array<int,array{role:string,content:string}> $history */
    public function chatWithArticle(string $articleText, array $history, string $userMessage, ?int $reviewId = null): array
    {
        if ($e = $this->guard('chat')) {
            return $e;
        }
        $system = "You answer questions about the following article. Be precise and cite sections when possible.\n\n"
            . "ARTICLE:\n" . $this->truncate($articleText, 80000);
        $messages = [];
        foreach ($history as $h) {
            $role = ($h['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) ($h['content'] ?? '')];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $res = $this->request($this->modelComplex, $system, $messages, $this->maxTokens, 'chat', $reviewId, false);
        return $res['ok']
            ? ['ok' => true, 'data' => $res['text']]
            : ['ok' => false, 'error' => $res['error']];
    }

    public function checkSemanticDuplicate(array $refA, array $refB, ?int $reviewId = null): array
    {
        if ($e = $this->guard('dedup')) {
            return $e;
        }
        $system = 'Decide whether the two records describe the SAME study. Respond ONLY with JSON: '
            . '{"duplicate":true,"confidence":0.0,"reason":""}.';
        $payload = json_encode([
            'a' => ['title' => $refA['title'] ?? '', 'abstract' => $refA['abstract'] ?? ''],
            'b' => ['title' => $refB['title'] ?? '', 'abstract' => $refB['abstract'] ?? ''],
        ], JSON_UNESCAPED_UNICODE);
        $res = $this->request($this->modelLight, $system,
            [['role' => 'user', 'content' => $payload]], 512, 'dedup', $reviewId, true);
        return $this->jsonResult($res);
    }

    /* ── Gating ────────────────────────────────────────────────────────── */

    /** @return array{ok:bool,error:string}|null */
    private function guard(string $feature): ?array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'error' => 'no_api_key'];
        }
        if ((bool) (setting('claude.feature.' . $feature) ?? true) === false) {
            return ['ok' => false, 'error' => 'feature_disabled'];
        }
        if ($this->budgetExceeded()) {
            return ['ok' => false, 'error' => 'budget_exceeded'];
        }
        return null;
    }

    private function budgetExceeded(): bool
    {
        $limit = (int) (setting('claude.monthly_limit_usd') ?? 0);
        if ($limit <= 0) {
            return false;
        }
        try {
            return AiUsage::monthlyCost() >= $limit;
        } catch (\Throwable) {
            return false;
        }
    }

    /* ── HTTP + parsing ────────────────────────────────────────────────── */

    /**
     * @return array{ok:bool,text:?string,json:mixed,error:?string}
     */
    private function request(string $model, string $system, array $messages, int $maxTokens, string $feature, ?int $reviewId, bool $expectJson = false): array
    {
        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ];

        $lastError = 'request failed';
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            [$status, $raw, $transport] = $this->post($body);

            if ($transport !== null) {
                $lastError = $transport;
            } elseif ($status === 200) {
                $decoded = json_decode($raw, true);
                $text = '';
                foreach ($decoded['content'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $text .= $block['text'];
                    }
                }
                $this->logUsage($reviewId, $feature, $model, $decoded['usage'] ?? []);
                return [
                    'ok'    => true,
                    'text'  => $text,
                    'json'  => $expectJson ? $this->extractJson($text) : null,
                    'error' => null,
                ];
            } else {
                $decoded = json_decode($raw, true);
                $lastError = $decoded['error']['message'] ?? ('HTTP ' . $status);
                if ($status < 500 && $status !== 429) {
                    break; // client error — don't retry
                }
            }
            // Exponential backoff: 2s, 4s, 8s.
            if ($attempt < self::MAX_RETRIES - 1) {
                sleep(2 ** ($attempt + 1));
            }
        }

        return ['ok' => false, 'text' => null, 'json' => null, 'error' => $lastError];
    }

    /** @return array{0:int,1:string,2:?string} */
    private function post(array $payload): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [$status, is_string($raw) ? $raw : '', $err];
    }

    private function logUsage(?int $reviewId, string $feature, string $model, array $usage): void
    {
        $in = (int) ($usage['input_tokens'] ?? 0);
        $out = (int) ($usage['output_tokens'] ?? 0);
        if ($in === 0 && $out === 0) {
            return;
        }
        try {
            AiUsage::record($reviewId, \SysRevAI\Core\Auth::id(), $feature, $model, $in, $out, $this->cost($model, $in, $out));
        } catch (\Throwable) {
            // accounting is best-effort
        }
    }

    public function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        [$inP, $outP] = self::PRICES[$model] ?? [0.0, 0.0];
        return round(($inputTokens / 1_000_000 * $inP) + ($outputTokens / 1_000_000 * $outP), 5);
    }

    /** Tolerantly extract a JSON object from a model reply (handles ```json fences). */
    public function extractJson(string $text): mixed
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $text = $m[1];
        } elseif (preg_match('/(\{.*\}|\[.*\])/s', $text, $m)) {
            $text = $m[1];
        }
        return json_decode($text, true);
    }

    /** @param array{ok:bool,json:mixed,error:?string} $res */
    private function jsonResult(array $res): array
    {
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error']];
        }
        if (!is_array($res['json'])) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        return ['ok' => true, 'data' => $res['json']];
    }

    private function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}
