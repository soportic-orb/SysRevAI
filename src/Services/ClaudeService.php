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

    public function suggestScreeningDecision(array $reference, array $protocol, ?int $reviewId = null, string $targetLanguage = 'en'): array
    {
        if ($e = $this->guard('screening')) {
            return $e;
        }
        $langName = self::languageName($targetLanguage);
        $system = "You assist with systematic-review title/abstract screening. Given the article and the "
            . "protocol, recommend whether to include it. You advise only; the reviewer decides. "
            . "Respond ONLY with JSON: {\"recommendation\":\"include|exclude|maybe\",\"confidence\":0.0,\"reason\":\"\"}. "
            . "Write the \"reason\" field in {$langName} so the reviewer can read it in their own language.";
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

    /** Friendly English name for the platform's supported locales, used in
     *  prompts so Claude knows which language to reply in. */
    private static function languageName(string $code): string
    {
        return match (strtolower($code)) {
            'ca' => 'Catalan',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'it' => 'Italian',
            'eu' => 'Basque',
            'gl' => 'Galician',
            default => 'English',
        };
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

    /**
     * Extract a systematic-review protocol from a free-form document
     * (e.g. a PDF / Word the user uploads when editing the protocol).
     * Returns the canonical 8 fields the UI fills in. Missing / unreported
     * fields come back as empty strings, not null, so the form can bind
     * them directly without extra coercion.
     *
     * @return array{ok:bool,data?:array<string,string>,error?:string}
     */
    public function extractProtocolFromText(string $documentText, ?int $reviewId = null): array
    {
        if ($e = $this->guard('extraction')) {
            return $e;
        }
        $system = "You are extracting the protocol of a systematic literature review from a free-form document "
            . "(PDF or Word). Return EXACTLY this JSON shape, all string fields, using empty strings when a "
            . "field is not present in the document — never null. Preserve the document's original language. "
            . "Inclusion / exclusion criteria should be returned as plain-text bullets separated by newlines.\n\n"
            . '{"question":"","population":"","intervention":"","comparison":"","outcome":"",'
            . '"study_design":"","inclusion_criteria":"","exclusion_criteria":""}';
        $res = $this->request(
            $this->modelComplex,
            $system,
            [['role' => 'user', 'content' => $this->truncate($documentText, 60000)]],
            2048,
            'extraction',
            $reviewId,
            true
        );
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error']];
        }
        if (!is_array($res['json'])) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        $expected = ['question', 'population', 'intervention', 'comparison', 'outcome', 'study_design', 'inclusion_criteria', 'exclusion_criteria'];
        $data = [];
        foreach ($expected as $k) {
            $v = $res['json'][$k] ?? '';
            $data[$k] = is_string($v) ? trim($v) : '';
        }
        return ['ok' => true, 'data' => $data];
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

    /**
     * Parse a free-form text block of references (any format — APA,
     * Vancouver, numbered list, Harvard, plain copy-paste from Word…) and
     * return a normalised array suitable for direct import. Sites can
     * paste a Word bibliography or a list of MLA citations and the model
     * extracts each reference into our canonical schema.
     *
     * @return array{ok:bool,refs?:array<int,array<string,mixed>>,error?:string}
     */
    public function extractReferencesFromText(string $text, ?int $reviewId = null): array
    {
        if ($e = $this->guard('extraction')) {
            return $e;
        }
        $system = "You parse a free-form bibliography pasted by a researcher. The text may use APA, "
            . "Vancouver, Harvard, MLA, Chicago, numbered lists, or no consistent style at all. "
            . "Identify every distinct bibliographic reference (article, book, report, web page, "
            . "preprint, conference paper) and return a JSON OBJECT with this exact shape — never "
            . "wrap in markdown fences, never include commentary:\n\n"
            . '{"references":[{"title":"","authors":["Last, First M.","Other, A."],"year":2024,'
            . '"journal":"","volume":"","issue":"","pages":"","doi":"","pmid":"","url":"",'
            . '"publication_type":"journal-article|book|preprint|report|conference|webpage|other",'
            . '"abstract":"","keywords":[]}]}'
            . "\n\nRules:\n"
            . " • Strings default to empty (\"\"), arrays default to []. Use null only for year when "
            . "you really cannot tell.\n"
            . " • Authors must be split into individual strings, family name first (\"Smith, J. A.\"). "
            . "Trim trailing periods. Expand \"et al.\" into a trailing \"et al.\" entry.\n"
            . " • For DOIs, return just the bare identifier (\"10.xxxx/yyyy\"), never the URL.\n"
            . " • If the text contains numbered or bulleted items, treat each item as one reference.\n"
            . " • If a fragment is clearly not a reference, skip it silently.";

        $res = $this->request(
            $this->modelComplex,
            $system,
            [['role' => 'user', 'content' => $this->truncate($text, 50000)]],
            4096,
            'extraction',
            $reviewId,
            true
        );
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error']];
        }
        $json = $res['json'];
        if (!is_array($json) || !isset($json['references']) || !is_array($json['references'])) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }

        $refs = [];
        foreach ($json['references'] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $title = trim((string) ($r['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $authors = [];
            foreach ((array) ($r['authors'] ?? []) as $a) {
                $a = trim((string) $a);
                if ($a !== '') {
                    $authors[] = $a;
                }
            }
            $keywords = [];
            foreach ((array) ($r['keywords'] ?? []) as $k) {
                $k = trim((string) $k);
                if ($k !== '') {
                    $keywords[] = $k;
                }
            }
            $year = $r['year'] ?? null;
            if (is_string($year)) {
                $year = preg_match('/\d{4}/', $year, $m) ? (int) $m[0] : null;
            } elseif (is_int($year)) {
                $year = $year >= 1500 && $year <= (int) date('Y') + 1 ? $year : null;
            } else {
                $year = null;
            }
            $doi = trim((string) ($r['doi'] ?? ''));
            if ($doi !== '') {
                $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) ?? $doi;
            }

            $refs[] = [
                'title'     => $title,
                'authors'   => $authors,
                'year'      => $year,
                'journal'   => trim((string) ($r['journal'] ?? '')),
                'volume'    => trim((string) ($r['volume'] ?? '')),
                'issue'     => trim((string) ($r['issue'] ?? '')),
                'pages'     => trim((string) ($r['pages'] ?? '')),
                'doi'       => $doi,
                'pmid'      => trim((string) ($r['pmid'] ?? '')),
                'url'       => trim((string) ($r['url'] ?? '')),
                'abstract'  => trim((string) ($r['abstract'] ?? '')),
                'keywords'  => $keywords,
            ];
        }
        return ['ok' => true, 'refs' => $refs];
    }

    /**
     * Scientific Copilot — conversational assistant for researchers inside
     * a specific review. Takes the review's protocol context so the model
     * can answer methodology questions, summarise the team's progress and
     * reason about included / excluded references. Recent chat history is
     * passed as the messages array; the system prompt stays fixed.
     *
     * @param array<string,mixed>                            $review     Review row.
     * @param array<string,string>                           $pico       PICO fields.
     * @param array<string,int>                              $metrics    Pre-computed counts.
     * @param array<int,array{role:string,content:string}>  $history    Prior turns (oldest first).
     * @param string                                         $userMessage Latest user message.
     * @return array{ok:bool,reply?:string,error?:string}
     */
    public function copilotChat(array $review, array $pico, array $metrics, array $history, string $userMessage, ?int $reviewId = null, ?array $pageContext = null): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }

        $context = [
            'review_title'        => (string) ($review['title'] ?? ''),
            'research_question'   => (string) ($review['question'] ?? ''),
            'population'          => $pico['population'] ?? '',
            'intervention'        => $pico['intervention'] ?? '',
            'comparison'          => $pico['comparison'] ?? '',
            'outcome'             => $pico['outcome'] ?? '',
            'study_design'        => $pico['study_design'] ?? '',
            'inclusion_criteria'  => (string) ($review['inclusion_criteria'] ?? ''),
            'exclusion_criteria'  => (string) ($review['exclusion_criteria'] ?? ''),
            'screening_mode'      => (string) ($review['screening_mode'] ?? ''),
            'metrics'             => $metrics,
        ];

        $system = "You are SysRevAI's Scientific Copilot, an assistant for researchers carrying out a systematic "
            . "literature review. Your role:\n"
            . " • Help with methodology questions about systematic reviews (PRISMA, screening, risk of bias, "
            . "data extraction, meta-analysis).\n"
            . " • Answer questions about THIS review based on the protocol context below.\n"
            . " • When a CURRENT PAGE CONTEXT is provided, you also know which article the user is "
            . "currently screening — answer questions about its title, abstract or (if the full-text excerpt "
            . "is present) its content.\n"
            . " • Suggest concrete next steps the team can take.\n"
            . " • If asked about a specific article that is NOT in your current page context, remind the user "
            . "to open that article first so you can see its metadata.\n\n"
            . "Tone: warm but professional, concise, evidence-aware. Reply in the same language as the user's "
            . "question. Use plain prose with short paragraphs; only use bullet lists when really helpful. "
            . "Never invent facts — if you don't know, say so and propose how to find out.\n\n"
            . "REVIEW CONTEXT (JSON):\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (is_array($pageContext) && $pageContext !== []) {
            $system .= "\n\nCURRENT PAGE CONTEXT (JSON) — the user is right now on this screen of the "
                . "platform; treat the reference fields, when present, as the article they are looking at:\n"
                . json_encode($pageContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $messages = [];
        // Keep at most the last 16 turns to stay within token budget while
        // still giving the model meaningful continuity across the thread.
        $tail = array_slice($history, -16);
        foreach ($tail as $h) {
            $role = ($h['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $content = (string) ($h['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Use the "light" model and a tighter token cap: conversational
        // answers are typically short and the Copilot needs to feel snappy.
        // The admin can swap the light slot to Sonnet/Opus if they want
        // more depth at the cost of latency.
        $res = $this->request($this->modelLight, $system, $messages, 800, 'copilot', $reviewId, false);
        return $res['ok']
            ? ['ok' => true, 'reply' => (string) $res['text']]
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

    /**
     * Public USD-per-million-tokens table for the supported models, so
     * the AI usage page can show users which rate was applied without
     * importing the private constant.
     *
     * @return array<string,array{0:float,1:float}>
     */
    public static function pricingTable(): array
    {
        return self::PRICES;
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
