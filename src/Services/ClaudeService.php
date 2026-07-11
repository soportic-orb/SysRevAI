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

    /**
     * Valid values for the chat `mode` parameter accepted by copilotChat(),
     * assistantChat() and articleChat() — single source of truth for the
     * controllers that sanitise the client-supplied mode before it reaches
     * modeOverlay(). 'default' is always allowed and isn't listed here.
     */
    public const CHAT_MODES = ['devil_advocate', 'socratic', 'fact_check', 'lit_review'];

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
        // 8192 gives headroom for long chat answers (full methodology
        // walk-throughs, PRISMA write-ups, multi-paragraph rewrites).
        // The admin can override this via claude.max_tokens.
        private readonly int $maxTokens = 8192,
    ) {
    }

    public static function fromSettings(): self
    {
        return new self(
            (string) (setting('claude.api_key') ?? ''),
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7'),
            (string) (setting('claude.model_light') ?? 'claude-haiku-4-5-20251001'),
            (int) (setting('claude.max_tokens') ?? 8192),
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

    /**
     * Structured peer-review rubric. Returns five 0-100 scores plus a
     * narrative summary, an obligatory devil's-advocate counter-argument
     * and an overall verdict. Used by the per-reference Peer Review view.
     *
     * The model is deliberately asked to grade only what the supplied
     * text supports — no inventing methodology details — and to write
     * the prose in the user's requested language so the rubric reads
     * naturally in the platform locale.
     *
     * Inspired by the 0-100 multi-perspective rubric from
     * imbad0202/academic-research-skills (CC-BY-NC 4.0).
     *
     * @param array<string,mixed> $reference Reference metadata (title, year, journal, authors)
     */
    public function peerReviewRubric(array $reference, string $articleText, string $targetLanguage = 'en', ?int $reviewId = null): array
    {
        if ($e = $this->guard('peer_review')) {
            return $e;
        }
        $langName = self::languageName($targetLanguage);
        $system = "You are a critical scientific peer reviewer. Read the supplied article and score "
            . "it on a 0-100 scale across five axes. Be honest: high scores must be earned by what "
            . "the text actually shows. Include a one-paragraph executive summary, a mandatory "
            . "devil's-advocate counter-argument that surfaces the strongest objection, and an "
            . "overall verdict (one short sentence). Write every prose field in {$langName}.\n\n"
            . "Respond ONLY with JSON of this exact shape:\n"
            . '{'
            . '"methodology":0, "clarity":0, "novelty":0, "evidence":0, "limitations":0, '
            . '"methodology_note":"", "clarity_note":"", "novelty_note":"", "evidence_note":"", "limitations_note":"", '
            . '"summary":"", "devils_advocate":"", "overall":""'
            . '}.';
        $payload = json_encode([
            'reference' => [
                'title'   => (string) ($reference['title']   ?? ''),
                'year'    => $reference['year'] ?? null,
                'journal' => (string) ($reference['journal'] ?? ''),
                'authors' => (array)  ($reference['authors'] ?? []),
                'doi'     => (string) ($reference['doi']     ?? ''),
            ],
            'article_text' => $this->truncate($articleText, 60000),
        ], JSON_UNESCAPED_UNICODE);
        $res = $this->request($this->modelComplex, $system,
            [['role' => 'user', 'content' => $payload]], $this->maxTokens, 'peer_review', $reviewId, true);
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

        // Documents may carry a single protocol OR a main + sub-studies
        // (umbrella reviews, parent protocols with branches, etc.). We ask
        // for one "primary" protocol the form pre-fills, plus an optional
        // list of "secondaries" the UI can offer to create as separate
        // reviews. Each entry adds a `title` so a secondary can be saved
        // on its own without the reviewer typing one.
        $system = "You are extracting systematic-review protocols from a free-form document (PDF or Word). "
            . "Most documents contain ONE protocol — return it as `primary` and leave `secondaries` empty. "
            . "When the document clearly describes additional distinct sub-studies (an umbrella review with "
            . "branches, a parent protocol with two or more sub-reviews, etc.), return the main one as "
            . "`primary` and each sub-study as its own entry in `secondaries`. Do NOT invent secondaries — "
            . "if you are unsure whether something is a separate review, leave `secondaries` empty.\n\n"
            . "Return EXACTLY this JSON shape, all string fields, using empty strings when a field is not "
            . "present in the document — never null. Preserve the document's original language. Inclusion / "
            . "exclusion criteria should be returned as plain-text bullets separated by newlines. The `title` "
            . "field is a short descriptive name for the review (e.g. 'Sub-study 1: Pediatric population').\n\n"
            . '{"primary":{"title":"","question":"","population":"","intervention":"","comparison":"",'
            . '"outcome":"","study_design":"","inclusion_criteria":"","exclusion_criteria":""},'
            . '"secondaries":[{"title":"","question":"","population":"","intervention":"","comparison":"",'
            . '"outcome":"","study_design":"","inclusion_criteria":"","exclusion_criteria":""}]}';
        $res = $this->request(
            $this->modelComplex,
            $system,
            [['role' => 'user', 'content' => $this->truncate($documentText, 60000)]],
            4096,
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

        $expected = ['title', 'question', 'population', 'intervention', 'comparison', 'outcome', 'study_design', 'inclusion_criteria', 'exclusion_criteria'];
        $normalise = static function (mixed $raw) use ($expected): array {
            if (!is_array($raw)) {
                $raw = [];
            }
            $out = [];
            foreach ($expected as $k) {
                $v = $raw[$k] ?? '';
                $out[$k] = is_string($v) ? trim($v) : '';
            }
            return $out;
        };

        // Backward-compat: accept the previous flat shape ({"question":...})
        // in case the model regresses, so the UI never gets a broken payload.
        $primary = isset($res['json']['primary']) && is_array($res['json']['primary'])
            ? $normalise($res['json']['primary'])
            : $normalise($res['json']);

        $secondaries = [];
        foreach ((array) ($res['json']['secondaries'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $row = $normalise($s);
            // Drop noise: skip rows the model padded in but never filled.
            if ($row['title'] === '' && $row['question'] === '' && $row['population'] === '') {
                continue;
            }
            $secondaries[] = $row;
            if (count($secondaries) >= 10) {
                break; // sanity cap; an umbrella review rarely has more
            }
        }

        return [
            'ok'   => true,
            'data' => [
                'primary'     => $primary,
                'secondaries' => $secondaries,
            ],
        ];
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
     * Re-format a free-text block of bibliographic references into the
     * requested citation style (APA, Vancouver, NLM, Chicago, MLA,
     * Harvard, AMA, IEEE). The model keeps the underlying bibliographic
     * data unchanged and only restyles the punctuation, ordering and
     * spelling-out conventions for each reference.
     *
     * @param string $text  one citation per line / paragraph
     * @param string $style canonical style key (apa, vancouver, nlm, …)
     * @return array{
     *   ok:bool,
     *   items?:list<array{original:string,normalized:string}>,
     *   error?:string
     * }
     */
    public function normalizeCitations(string $text, string $style, ?int $reviewId = null): array
    {
        if ($e = $this->guard('extraction')) {
            return $e;
        }
        $styleLabel = match (strtolower($style)) {
            'apa'        => 'APA 7th edition',
            'vancouver'  => 'Vancouver',
            'nlm'        => 'NLM (National Library of Medicine)',
            'chicago'    => 'Chicago author-date',
            'mla'        => 'MLA 9th edition',
            'harvard'    => 'Harvard',
            'ama'        => 'AMA 11th edition',
            'ieee'       => 'IEEE',
            default      => 'APA 7th edition',
        };

        $system = "You are a bibliographic-citation normaliser. You receive a free-text block of references "
            . "(in any style or no consistent style at all) and re-format every one of them to the "
            . "{$styleLabel} citation style. NEVER invent missing fields — if a reference is incomplete in "
            . "the source, leave the corresponding part blank in the normalised output. Preserve every "
            . "identifier (DOI, PMID, URL) you can find. One entry per distinct reference. Return ONLY a "
            . "JSON object with this exact shape, no markdown fences, no commentary:\n\n"
            . '{"citations":[{"original":"<the reference as it arrived>","normalized":"<the reference '
            . 'reformatted to ' . $styleLabel . '>"}]}';

        // Chunk so the response can comfortably fit inside max_tokens — a
        // typical normalised reference is 100-300 tokens long.
        $chunks = $this->splitBibliographyIntoChunks($text);
        if ($chunks === []) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }

        $items = [];
        $lastError = null;
        $okChunks = 0;
        foreach ($chunks as $chunk) {
            $res = $this->request(
                $this->modelComplex,
                $system,
                [['role' => 'user', 'content' => $this->truncate($chunk, 50000)]],
                8192,
                'extraction',
                $reviewId,
                true
            );
            if (!$res['ok']) {
                $lastError = $res['error'];
                if (in_array($lastError, ['no_api_key', 'feature_disabled', 'budget_exceeded'], true)) {
                    return ['ok' => false, 'error' => $lastError];
                }
                continue;
            }
            $json = $res['json'];
            if (!is_array($json) || !is_array($json['citations'] ?? null)) {
                $lastError = 'invalid_json';
                continue;
            }
            $okChunks++;
            foreach ($json['citations'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $original   = trim((string) ($row['original']   ?? ''));
                $normalized = trim((string) ($row['normalized'] ?? ''));
                if ($normalized === '') {
                    continue;
                }
                $items[] = [
                    'original'   => $original,
                    'normalized' => $normalized,
                ];
            }
        }

        if ($okChunks === 0 && $items === []) {
            return ['ok' => false, 'error' => $lastError ?? 'invalid_json'];
        }

        return ['ok' => true, 'items' => $items];
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

        // Bibliographies pasted from Word, Zotero, journal sites, etc. often
        // contain 20-100+ references. A single Claude call capped at 4096
        // output tokens silently truncates the JSON array mid-way once we
        // cross ~15 refs, and the partial array fails to parse — which is
        // exactly the "nothing imported" symptom users reported.
        //
        // Strategy: split the input on reference boundaries (numbered list
        // markers, blank-line gaps) into chunks of ~3500 chars, run each
        // chunk through Claude with a roomy 8192 max_tokens, and merge the
        // results. Hard infrastructure errors (no key / disabled / budget)
        // short-circuit the loop; transient per-chunk errors only drop the
        // failed slice so the rest of the bibliography still imports.
        $chunks = $this->splitBibliographyIntoChunks($text);
        if ($chunks === []) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }

        $allRefs = [];
        $lastError = null;
        $okChunks = 0;
        foreach ($chunks as $chunk) {
            $r = $this->extractReferencesChunk($chunk, $reviewId);
            if ($r['ok']) {
                $okChunks++;
                foreach ($r['refs'] as $ref) {
                    $allRefs[] = $ref;
                }
                continue;
            }
            $lastError = $r['error'];
            // Hard config errors apply to every chunk — abort early.
            if (in_array($lastError, ['no_api_key', 'feature_disabled', 'budget_exceeded'], true)) {
                return ['ok' => false, 'error' => $lastError];
            }
        }

        if ($okChunks === 0) {
            return ['ok' => false, 'error' => $lastError ?? 'invalid_json'];
        }

        return ['ok' => true, 'refs' => $allRefs];
    }

    /**
     * Single-chunk extraction. Shares the schema prompt with the chunked
     * top-level entry point; the output cap is bumped to 8192 so a chunk
     * of ~15 references comfortably fits inside a single response.
     *
     * @return array{ok:bool,refs?:array<int,array<string,mixed>>,error?:string}
     */
    private function extractReferencesChunk(string $text, ?int $reviewId): array
    {
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
            8192,
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
    public function copilotChat(array $review, array $pico, array $metrics, array $history, string $userMessage, ?int $reviewId = null, ?array $pageContext = null, string $mode = 'default'): array
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

        $system .= self::modeOverlay($mode);

        // Agentic-actions catalogue — the model can now PROPOSE
        // concrete write-actions the user approves with one click.
        // The system-prompt block here documents the JSON envelope and
        // the available tools; the server still validates everything
        // through CopilotActionService::validate() before persisting.
        $system .= CopilotActionService::systemPromptBlock();

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

        // 8 k output cap (was 800; long answers were truncating). We ask
        // for JSON so the front end can recognise both plain replies
        // and the {reply,action} agentic envelope. expectJson:true makes
        // request() parse the body via extractJson before returning.
        $res = $this->request($this->modelLight, $system, $messages, 8000, 'copilot', $reviewId, true);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error']];
        }
        $reply  = '';
        $action = null;
        if (is_array($res['json'] ?? null)) {
            $reply = trim((string) ($res['json']['reply'] ?? ''));
            if (is_array($res['json']['action'] ?? null)) {
                $action = $res['json']['action'];
            }
        }
        // Fallback: the model returned plain text (no JSON envelope).
        // Treat the whole text as the reply with no proposed action so
        // the conversational path still works.
        if ($reply === '' && is_string($res['text'] ?? null)) {
            $reply = trim((string) $res['text']);
        }
        return ['ok' => true, 'reply' => $reply, 'action' => $action];
    }

    /**
     * Global Copilot — same chat surface, no review context. Use this when
     * the user is anywhere on the platform that isn't a /reviews/{id}/*
     * page. The system prompt steers the model toward platform how-to
     * answers and general research-methodology questions, and asks it to
     * defer review-specific questions until the user opens the review.
     *
     * @param array<int,array{role:string,content:string}> $history
     * @return array{ok:bool,reply?:string,error?:string}
     */
    public function assistantChat(array $history, string $userMessage, string $mode = 'default', ?array $pageContext = null): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }

        $system = "You are SysRevAI's Scientific Copilot, available platform-wide. Your role outside of "
            . "a specific review:\n"
            . " • Explain how to use SysRevAI features: creating a review, importing references "
            . "(CSV / RIS / PubMed exports), screening (single, double-blind, third-reviewer), full-text "
            . "retrieval, risk-of-bias tools, data extraction, exports (PRISMA flow, CSV, citations), "
            . "team invitations, EvidenceHunt mode, Copilot itself.\n"
            . " • Answer general research-methodology questions: PRISMA, Cochrane, PICO formulation, "
            . "search strategies, study design appraisal, RoB tools (RoB 2, ROBINS-I, Newcastle-Ottawa, "
            . "JBI), meta-analysis basics, GRADE.\n"
            . " • If the user asks about a SPECIFIC review they're working on, ask them to open that "
            . "review first so the Copilot can load its protocol context (PICO, criteria, metrics).\n\n"
            . "Tone: warm but professional, concise, evidence-aware. Reply in the same language as the "
            . "user's question. Use plain prose with short paragraphs; bullet lists only when really "
            . "helpful. Never invent facts — if you don't know, say so and propose how to find out.";

        // When the user is on a page that supplies a rich context (today
        // the collaborative article editor), embed it so the floating
        // Copilot can hold a grounded conversation about the work the
        // user is actually doing.
        $system .= $this->pageContextOverlay($pageContext);
        $system .= self::modeOverlay($mode);

        $messages = [];
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

        // 8 k output cap — matches copilotChat() so the global (no-review)
        // Copilot keeps parity with the review-scoped one and stops
        // truncating long answers (PRISMA checklists, methods write-ups).
        $res = $this->request($this->modelLight, $system, $messages, 8000, 'copilot', null, false);
        return $res['ok']
            ? ['ok' => true, 'reply' => (string) $res['text']]
            : ['ok' => false, 'error' => $res['error']];
    }

    /**
     * Article-scoped Copilot. The full extracted text of the article is
     * embedded in the system prompt so the model can ground every reply
     * in the user's actual paper — quote sections, point to specific
     * sentences, recommend rewrites. Reuses the Copilot feature toggle
     * because the surface is the same chat UX.
     *
     * @param array<string,mixed>                            $article  Article row from the DB.
     * @param array<int,array{role:string,content:string}>  $history  Prior turns (oldest first).
     */
    public function articleChat(array $article, array $history, string $userMessage, string $mode = 'default', ?array $report = null, array $secondaryDocuments = []): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }

        $title = (string) ($article['title'] ?? 'Untitled article');
        $text  = (string) ($article['extracted_text'] ?? '');

        $system = "You are SysRevAI's Article Copilot — a writing partner for the researcher who has "
            . "uploaded the article you're about to see. Your job:\n"
            . " • Ground every answer in the article's actual text. Quote short passages when you can.\n"
            . " • Help the user understand, critique, improve and revise the manuscript: structure, "
            . "argumentation, methodology, clarity, citation, language.\n"
            . " • If asked something the article doesn't cover, say so plainly rather than inventing.\n"
            . " • Reply in the same language as the user's question. Plain prose, short paragraphs; "
            . "bullet lists only when really helpful.\n\n"
            . "ARTICLE TITLE: " . $title . "\n\n"
            . "ARTICLE TEXT (verbatim, may have been truncated for length):\n"
            . $this->truncate($text, 60000);

        // Secondary documents — additional reference material the user
        // attached so the Copilot can do cross-document comparisons
        // (methodology contrasts, citation lookups, guideline checks).
        // Each entry already comes truncated from
        // ArticleDocument::copilotPayload(); we just frame them so the
        // model knows they're SUPPORTING material, not the main paper.
        if ($secondaryDocuments !== []) {
            $system .= "\n\nADDITIONAL REFERENCE DOCUMENTS (uploaded by the user as supporting "
                . "material — quote them when the user asks comparative questions, but ALWAYS treat "
                . "the ARTICLE TEXT above as the manuscript under discussion):";
            $i = 0;
            foreach ($secondaryDocuments as $doc) {
                $i++;
                $name = (string) ($doc['filename'] ?? ('Document ' . $i));
                $body = (string) ($doc['text'] ?? '');
                if (trim($body) === '') {
                    continue;
                }
                $system .= "\n\n--- Document " . $i . ' — "' . $name . "\":\n" . $body;
            }
        }

        // When a critical report exists for this article, feed a structured
        // summary of it into the system prompt so Copilot can guide the
        // user through the report's recommendations phase by phase when
        // asked. We pass the report itself, not a description of it, so
        // the model can reference exact axis notes and recommendations.
        if ($report !== null) {
            $system .= "\n\nCRITICAL REPORT (use this as the structured guide. When the user agrees "
                . "to work on the article based on it, propose improvements in phases following the "
                . "report's section-by-section recommendations, starting with high-priority items. "
                . "Quote the report's exact wording when useful):\n"
                . $this->truncate(self::summariseReportForPrompt($report), 12000);
        }

        $system .= self::modeOverlay($mode);

        $messages = [];
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

        // 8 k cap — same reasoning as copilotChat/assistantChat. Article
        // discussions often want full paragraph rewrites or section
        // walk-throughs that don't fit in 1.2 k tokens.
        $res = $this->request($this->modelLight, $system, $messages, 8000, 'copilot', null, false);
        return $res['ok']
            ? ['ok' => true, 'reply' => (string) $res['text']]
            : ['ok' => false, 'error' => $res['error']];
    }

    /**
     * Compact human-readable rendering of the cached critical report so
     * it fits in the chat system prompt without dragging in the full
     * structured JSON. Format mirrors the web view's layout so the model
     * can reference it the way the user has seen it.
     *
     * @param array<string,mixed> $r
     */
    private static function summariseReportForPrompt(array $r): string
    {
        $lines = [];
        if (!empty($r['overall'])) {
            $lines[] = 'Overall verdict: ' . trim((string) $r['overall']);
        }
        $axes = ['methodology', 'clarity', 'novelty', 'evidence', 'limitations'];
        $scores = [];
        foreach ($axes as $axis) {
            $scores[] = ucfirst($axis) . ' ' . (int) ($r[$axis] ?? 0) . '/100';
        }
        $lines[] = 'Scores: ' . implode(' · ', $scores);
        foreach ($axes as $axis) {
            $note = trim((string) ($r[$axis . '_note'] ?? ''));
            if ($note !== '') {
                $lines[] = ucfirst($axis) . ' — ' . $note;
            }
        }
        foreach ([
            'summary'                => 'Executive summary',
            'methodology_critique'   => 'Methodology critique',
            'literature_positioning' => 'Literature positioning',
            'publication_outlook'    => 'Publication outlook',
            'devils_advocate'        => "Devil's advocate",
        ] as $key => $label) {
            $val = trim((string) ($r[$key] ?? ''));
            if ($val !== '') {
                $lines[] = $label . ':' . "\n" . $val;
            }
        }
        foreach ([
            'key_strengths'         => 'Key strengths',
            'key_weaknesses'        => 'Key weaknesses',
            'statistical_concerns'  => 'Statistical concerns',
            'ethical_concerns'      => 'Ethical concerns',
            'reproducibility_notes' => 'Reproducibility notes',
        ] as $key => $label) {
            $items = (array) ($r[$key] ?? []);
            if ($items === []) {
                continue;
            }
            $lines[] = $label . ':';
            foreach ($items as $it) {
                $lines[] = '  • ' . trim((string) $it);
            }
        }
        $recs = (array) ($r['recommendations'] ?? []);
        if ($recs !== []) {
            $lines[] = 'Section-by-section recommendations:';
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $lines[] = '  [' . $sec . ']';
                }
                foreach ((array) ($rec['items'] ?? []) as $it) {
                    if (is_array($it)) {
                        $txt  = trim((string) ($it['text'] ?? ''));
                        $prio = strtoupper((string) ($it['priority'] ?? 'medium'));
                        $lines[] = '    - [' . $prio . '] ' . $txt;
                    } else {
                        $lines[] = '    - ' . trim((string) $it);
                    }
                }
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Fill the PROSPERO or OSF registration field map from the existing
     * review protocol (PICO/PCC, question, criteria, search context).
     *
     * Returns ['ok' => true, 'data' => [fieldId => string, …]] on success.
     * The field set is dictated by RegistrationFields::schemaFor($kind);
     * any keys the model emits outside that set are dropped server-side.
     *
     * @param array<string,mixed>             $review   Reviews row.
     * @param array<string,string>            $pico     Decoded PICO/PCC map.
     * @param array<int,array<string,mixed>>  $fields   Field schema entries.
     * @param string                          $kind     'prospero' | 'osf'.
     * @param string                          $locale   Target reply language.
     */
    public function fillRegistrationFields(array $review, array $pico, array $fields, string $kind, string $locale = 'en'): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }

        $isOsf = $kind === 'osf';
        $registry = $isOsf ? 'OSF (Open Science Framework) preregistration for a SCOPING REVIEW' : 'PROSPERO registration for a SYSTEMATIC REVIEW';

        $schemaLines = [];
        foreach ($fields as $f) {
            $schemaLines[] = '  • ' . $f['id'] . ' — '
                . ($f['type'] === 'textarea' ? 'multi-paragraph text' : 'short single-line text');
        }

        $system = "You are SysRevAI's registration assistant. The user is preparing the "
            . $registry . " for an existing protocol stored on this platform. Your job is to "
            . "PRE-FILL every registration field as accurately as possible from the protocol "
            . "context that follows.\n\n"
            . "RULES:\n"
            . " • Reply ONLY with a JSON object whose keys are the field ids listed below. No "
            . "prose outside the JSON, no markdown, no comments.\n"
            . " • Every value must be a string. Use \"\" for fields you genuinely cannot infer "
            . "from the protocol — never invent dates, authors, funding, or numbers.\n"
            . " • Keep wording professional and consistent with " . ($isOsf ? "JBI / PRISMA-ScR" : "Cochrane / PRISMA") . " conventions.\n"
            . " • Reply in the user's locale: " . $locale . ". Field ids stay in English.\n"
            . " • Multi-paragraph fields may use \\n\\n between paragraphs; don't include HTML.\n\n"
            . "FIELDS TO FILL (id — kind):\n" . implode("\n", $schemaLines);

        $picoLines = [];
        foreach ($pico as $k => $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $picoLines[] = '  ' . $k . ': ' . $this->truncate($v, 1200);
            }
        }

        $protocol = "REVIEW TYPE: " . ($isOsf ? "scoping review" : "systematic review") . "\n"
            . "TITLE: " . ($review['title'] ?? '—') . "\n"
            . "QUESTION: " . trim((string) ($review['question'] ?? '')) . "\n"
            . "INCLUSION CRITERIA:\n" . $this->truncate((string) ($review['inclusion_criteria'] ?? ''), 4000) . "\n"
            . "EXCLUSION CRITERIA:\n" . $this->truncate((string) ($review['exclusion_criteria'] ?? ''), 4000) . "\n"
            . "PICO / PCC:\n" . ($picoLines !== [] ? implode("\n", $picoLines) : '  (empty)');

        // 32 multi-paragraph PROSPERO fields can easily blow past 4 k
        // output tokens — the response got truncated mid-JSON and
        // json_decode silently failed. 16 k is comfortable for Haiku
        // 4.5 and still well inside its quota. Two attempts so a
        // transient 5xx / overload is retried automatically.
        $res = $this->request(
            $this->modelLight,
            $system,
            [['role' => 'user', 'content' => $protocol]],
            16000,
            'copilot',
            null,
            true,
            240,
            2
        );
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'request_failed'];
        }

        $allowed = [];
        foreach ($fields as $f) {
            $allowed[$f['id']] = true;
        }
        $out = [];

        // Happy path: structured JSON parsed by request()'s extractJson.
        if (is_array($res['json'])) {
            foreach ($res['json'] as $k => $v) {
                if (!is_string($k) || !isset($allowed[$k])) {
                    continue;
                }
                if (is_string($v)) {
                    $out[$k] = trim($v);
                } elseif (is_scalar($v)) {
                    $out[$k] = trim((string) $v);
                }
            }
        }

        // Fallback: extractJson failed (truncated payload, prose around
        // JSON, etc.). Try to pull individual "key": "value" pairs out
        // of the raw text so the user still gets partial credit.
        if ($out === [] && is_string($res['text'] ?? null) && $res['text'] !== '') {
            foreach (self::recoverFieldsFromText((string) $res['text'], array_keys($allowed)) as $k => $v) {
                $out[$k] = $v;
            }
        }

        if ($out === []) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        return ['ok' => true, 'data' => $out];
    }

    /**
     * Best-effort scan of a possibly-truncated AI response for the
     * "<key>": "<value>" pairs we know we asked for. Used as a fallback
     * for fillRegistrationFields when the JSON parser couldn't put the
     * whole document back together. Walks the text key-by-key so each
     * field is independently recoverable.
     *
     * @param  array<int,string> $keys
     * @return array<string,string>
     */
    private static function recoverFieldsFromText(string $text, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            // Match  "key" : " ... "  with JSON-escaped backslashes /
            // quotes inside the value. Non-greedy stop at the next
            // unescaped quote, then JSON-decode the matched literal so
            // \n / \" / \\ etc. come back through.
            $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s';
            if (preg_match($pattern, $text, $m)) {
                $literal = '"' . $m[1] . '"';
                $decoded = json_decode($literal, true);
                if (is_string($decoded)) {
                    $value = trim($decoded);
                    if ($value !== '') {
                        $out[$key] = $value;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Extract verbatim search-strategy syntaxes from a review's
     * protocol context for each supported bibliographic database.
     *
     * The protocol context is built by the caller (controller) from the
     * review's question, PICO/PCC, inclusion/exclusion criteria and any
     * notes — whatever literal protocol text we have at hand. The model
     * is told NOT to invent a syntax when the protocol doesn't contain
     * one for that database; empty strings are the correct answer in
     * that case, and the caller drops them.
     *
     * @param array<string,mixed>             $review     Reviews row.
     * @param string                          $protocolText Concatenated protocol context.
     * @param array<int,array<string,string>> $databases  Catalogue entries (key + label + syntax_hint).
     * @return array{ok:bool,data?:array<string,string>,error?:string}
     */
    public function extractSearchSyntaxes(array $review, string $protocolText, array $databases): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }

        $catalogue = [];
        foreach ($databases as $db) {
            $catalogue[] = '  • ' . $db['key'] . ' — ' . $db['label']
                . ' (' . ($db['syntax_hint'] ?? '') . ')';
        }

        $system = "You are SysRevAI's search-strategy extractor. The user has a research-review "
            . "protocol and wants you to PULL OUT the literal database search syntaxes embedded "
            . "in it — one per supported bibliographic database.\n\n"
            . "DATABASES (key — label):\n" . implode("\n", $catalogue) . "\n\n"
            . "RULES:\n"
            . " • Reply ONLY with a JSON object whose keys are the database `key`s above. No "
            . "prose, no markdown, no comments outside the JSON.\n"
            . " • The value for each key must be a string: the EXACT verbatim search-strategy "
            . "syntax found in the protocol for that database. Preserve operators, field tags, "
            . "parentheses and line breaks (use \\n inside the JSON string).\n"
            . " • If the protocol does NOT contain a search syntax for a given database, return "
            . "\"\" for that key. NEVER invent or interpolate a syntax. If unsure, return \"\".\n"
            . " • Use the database hint above only to RECOGNISE the syntax, not to author one.\n"
            . " • Drop boilerplate like \"Search ran on …\" or \"Strategy adapted from …\" — keep "
            . "only the actual query.";

        $title = (string) ($review['title'] ?? 'Untitled review');
        $kind  = (string) ($review['kind']  ?? 'systematic');

        $body = "REVIEW TITLE: " . $title . "\n"
            . "REVIEW TYPE: " . $kind . "\n\n"
            . "PROTOCOL TEXT (verbatim):\n" . $this->truncate($protocolText, 60000);

        $res = $this->request(
            $this->modelLight,
            $system,
            [['role' => 'user', 'content' => $body]],
            6000,
            'copilot',
            null,
            true,
            240,
            1
        );
        if (!$res['ok'] || !is_array($res['json'])) {
            return ['ok' => false, 'error' => $res['error'] ?? 'invalid_json'];
        }

        $allowed = [];
        foreach ($databases as $db) {
            $allowed[(string) $db['key']] = true;
        }
        $out = [];
        foreach ($res['json'] as $k => $v) {
            if (!is_string($k) || !isset($allowed[$k])) {
                continue;
            }
            if (is_string($v)) {
                $out[$k] = trim($v);
            }
        }
        return ['ok' => true, 'data' => $out];
    }

    /**
     * Translate plain text from $source into $target using Claude.
     *
     * Used as the default translation engine when the admin hasn't
     * uploaded a Google Cloud service-account JSON — the platform
     * still ships a working "Translate" button on Summary / Peer-
     * review pages without requiring extra setup.
     *
     * Source 'auto' is honoured by leaving the language detection
     * to the model. The system prompt insists on returning only
     * the translation, no quoting or commentary, so the response
     * can be cached and re-rendered verbatim.
     *
     * @return array{ok:bool,text:?string,error:?string}
     */
    public function translateText(string $text, string $target, string $source = 'auto'): array
    {
        if ($e = $this->guard('copilot')) {
            // guard returns the same shape ClaudeService methods use
            // ({ok:false,error:…}); collapse into the translate shape
            // so the caller never has to handle two error envelopes.
            return ['ok' => false, 'text' => null, 'error' => (string) ($e['error'] ?? 'feature_disabled')];
        }
        if (trim($text) === '') {
            return ['ok' => true, 'text' => $text, 'error' => null];
        }
        $sourceClause = $source !== '' && $source !== 'auto'
            ? "from $source "
            : '(auto-detect the source language) ';
        $system = "You are a professional translator. Translate the user message "
            . $sourceClause . "into " . $target . ". "
            . "Rules: reply ONLY with the translated text — no quoting, no commentary, "
            . "no language-detection note. Preserve every line break and every paragraph "
            . "break. Preserve any markdown formatting (asterisks, lists, headings, links) "
            . "byte-for-byte. Keep proper nouns, citations, units and numbers untouched.";
        // 16 k output cap so long article abstracts / peer-review
        // sections aren't truncated mid-sentence.
        $res = $this->request(
            $this->modelLight,
            $system,
            [['role' => 'user', 'content' => $text]],
            16000,
            'content',
            null,
            false,
            240,
            2
        );
        if (!$res['ok']) {
            return ['ok' => false, 'text' => null, 'error' => (string) ($res['error'] ?? 'translate_failed')];
        }
        return ['ok' => true, 'text' => trim((string) $res['text']), 'error' => null];
    }

    /**
     * Structured critical report for an uploaded article. Beyond the
     * five 0-100 axis scores, the model returns a multi-paragraph
     * analysis per axis, a 3-5-sentence executive summary, a mandatory
     * devil's-advocate counter-argument, an overall verdict, ordered
     * lists of strengths / weaknesses / statistical / ethical /
     * reproducibility concerns, prose on literature positioning and
     * publication outlook, and section-by-section recommendations with
     * a per-item priority flag.
     *
     * The model is asked to ground every claim in the supplied text
     * (no inventing methodology details) and to write all prose in the
     * user's locale so the report reads naturally in the platform
     * language. Inspired by the multi-perspective critical-review
     * prompt pattern from imbad0202/academic-research-skills (CC-BY-NC 4.0).
     *
     * @param array<string,mixed> $article Article row (title, extracted_text).
     */
    public function articleCriticalReport(array $article, string $targetLanguage = 'en'): array
    {
        if ($e = $this->guard('peer_review')) {
            return $e;
        }
        $title = (string) ($article['title'] ?? 'Untitled article');
        $text  = (string) ($article['extracted_text'] ?? '');
        $langName = self::languageName($targetLanguage);

        $system = "You are an experienced peer reviewer producing a DEEP, exhaustive critical "
            . "report on a manuscript the author has uploaded for self-review. Read the supplied "
            . "article carefully and ground every claim in what the text actually shows — never "
            . "invent methodology, results or citations. The report must be substantive: short, "
            . "vague answers are not acceptable.\n\n"
            . "Required content (write every prose field in {$langName}):\n"
            . " • Five 0-100 scores (methodology, clarity, novelty, evidence, limitations). High "
            . "scores must be earned. For EACH axis, write 2-4 sentences (not just one) explaining "
            . "what specifically the manuscript does well or poorly on that dimension.\n"
            . " • A 3-5 sentence executive summary that states the contribution, the methods at a "
            . "high level, the main finding and its significance.\n"
            . " • An overall verdict — ONE short sentence (max 25 words) capturing the decision a "
            . "reviewer would reach today.\n"
            . " • A MANDATORY devil's-advocate paragraph: the strongest single objection a hostile "
            . "reviewer would raise, written as a coherent counter-argument (not a list).\n"
            . " • 4-7 specific key strengths and 4-7 specific key weaknesses, as concise bullet "
            . "points. Quote or paraphrase short evidence from the text where possible.\n"
            . " • 2-4 paragraphs of methodology critique covering study design, sample, controls, "
            . "measures, analytical strategy, blinding and any threats to validity.\n"
            . " • 3-6 statistical concerns: sample size / power, multiple testing, effect sizes, "
            . "confidence intervals, model assumptions, missing data handling, etc.\n"
            . " • 2-5 ethical concerns: IRB approval, consent, conflicts of interest, data sharing "
            . "consent, animal welfare, equity issues. Mark 'none observed' if truly applicable.\n"
            . " • 3-6 reproducibility notes: data availability, code availability, materials / "
            . "protocols, statistical scripts, pre-registration, transparent reporting.\n"
            . " • 2-3 paragraphs on literature positioning — how this work fits into prior research, "
            . "what it cites and what notable references are missing.\n"
            . " • 1-2 paragraphs on publication outlook: realistic chance of acceptance, suggested "
            . "tier / journal type, and the single biggest change needed to publish.\n"
            . " • Section-by-section recommendations. Use only the sections the manuscript actually "
            . "has (Abstract, Introduction, Methods, Results, Discussion, Conclusion, References, "
            . "Figures / Tables, etc.). For each section give 3-8 concrete, rewrite-ready items. "
            . "Each item has a priority: 'high' (publication blocker), 'medium' (substantive "
            . "improvement), 'low' (polish / style).\n\n"
            . "Respond ONLY with JSON of this exact shape:\n"
            . '{'
            . '"methodology":0, "clarity":0, "novelty":0, "evidence":0, "limitations":0, '
            . '"methodology_note":"", "clarity_note":"", "novelty_note":"", "evidence_note":"", "limitations_note":"", '
            . '"summary":"", "overall":"", "devils_advocate":"", '
            . '"key_strengths":[""], "key_weaknesses":[""], '
            . '"methodology_critique":"", '
            . '"statistical_concerns":[""], "ethical_concerns":[""], "reproducibility_notes":[""], '
            . '"literature_positioning":"", "publication_outlook":"", '
            . '"recommendations":[{"section":"","items":[{"text":"","priority":"high"}]}]'
            . '}.';
        $payload = json_encode([
            'article_title' => $title,
            'article_text'  => $this->truncate($text, 60000),
        ], JSON_UNESCAPED_UNICODE);
        // 6000 tokens of Opus output can take ~120 s to stream, so the
        // default cURL timeout of 120 s clips the response mid-stream and
        // the retry loop multiplies the wall time until PHP's
        // set_time_limit kills the worker. Give this call a 300 s budget,
        // a single attempt, and a token cap that fits comfortably inside.
        $res = $this->request(
            $this->modelComplex,
            $system,
            [['role' => 'user', 'content' => $payload]],
            min(6000, max(4096, $this->maxTokens)),
            'peer_review',
            null,
            true,
            300,
            1
        );
        return $this->jsonResult($res);
    }

    /**
     * Selection-aware editor assistant. Given the full article HTML, an
     * optional selected fragment and the user's instruction, Copilot
     * returns a structured edit: a small block of HTML to insert or
     * use as a replacement for the selection, plus a human-readable
     * explanation. The editor renders the response as an Accept /
     * Reject preview so the user always reviews the change before it
     * lands in the document.
     *
     * When `$report` is supplied (the cached critical report for the
     * article), it's embedded so the assistant can ground its
     * suggestions in the report's recommendations.
     *
     * @param array<string,mixed>      $article  Article row (title, etc.).
     * @param string                   $fullHtml Current full document HTML.
     * @param string                   $selectionHtml The user's selection (empty for whole-doc requests).
     * @param string                   $userPrompt    Free-text instruction from the user.
     * @param array<string,mixed>|null $report   Optional cached critical report.
     * @return array{ok:bool,data?:array{action:string,html:string,explanation:string,scope:string},error?:string}
     */
    public function articleEditorEdit(array $article, string $fullHtml, string $selectionHtml, string $userPrompt, ?array $report = null): array
    {
        if ($e = $this->guard('copilot')) {
            return $e;
        }
        $title = (string) ($article['title'] ?? 'Untitled article');
        $hasSelection = trim(strip_tags($selectionHtml)) !== '';

        $system = "You are SysRevAI's Article Copilot in collaborative-editor mode. The user is "
            . "writing or revising a manuscript inside a rich-text editor and needs you to "
            . "PROPOSE an edit they can review.\n\n"
            . "Rules:\n"
            . " • Reply ONLY with JSON of the exact shape: "
            . '{"action":"replace_selection|insert_after_selection|append_to_document",'
            . '"html":"…","explanation":"…","scope":"selection|document"}.\n'
            . " • `action`: when a selection exists, default to `replace_selection`. Use "
            . "`insert_after_selection` only when the user explicitly asks to add a paragraph, "
            . "table or note next to the selection. Use `append_to_document` when there's no "
            . "selection.\n"
            . " • `html`: the EXACT HTML that should be inserted, using only the tags TinyMCE "
            . "renders cleanly — p, h1-h4, strong, em, u, ul, ol, li, blockquote, table, thead, "
            . "tbody, tr, th, td, br. Do not wrap your response in <html> or <body>. Do not "
            . "include scripts, styles or inline event handlers.\n"
            . " • `explanation`: one paragraph in the user's language explaining what the change "
            . "does and why. Keep it under 80 words.\n"
            . " • `scope`: `selection` when you're rewriting just the selected fragment, "
            . "`document` when you're adding new content.\n\n"
            . "Ground every change in the full article text you're about to see. If a critical "
            . "report is supplied, prefer suggestions that move the manuscript toward its "
            . "recommendations. Never invent results, numbers or citations that aren't "
            . "already in the document.";

        if ($report !== null) {
            $system .= "\n\nCRITICAL REPORT FOR THIS ARTICLE:\n"
                . $this->truncate(self::summariseReportForPrompt($report), 8000);
        }

        $system .= "\n\nARTICLE TITLE: " . $title;
        $system .= "\n\nFULL DOCUMENT HTML (truncated for length):\n"
            . $this->truncate($fullHtml, 40000);

        $userPayload = ($hasSelection
                ? ("SELECTED FRAGMENT (HTML):\n" . $this->truncate($selectionHtml, 8000) . "\n\n")
                : ("NO ACTIVE SELECTION. The whole document is in scope.\n\n"))
            . "USER INSTRUCTION:\n" . trim($userPrompt);

        $res = $this->request(
            $this->modelLight,
            $system,
            [['role' => 'user', 'content' => $userPayload]],
            1600,
            'copilot',
            null,
            true,
            180,
            1
        );
        if (!$res['ok'] || !is_array($res['json'])) {
            return ['ok' => false, 'error' => $res['error'] ?? 'invalid_json'];
        }
        $data = $res['json'];
        return ['ok' => true, 'data' => [
            'action'      => self::editorAction((string) ($data['action'] ?? ''), $hasSelection),
            'html'        => self::sanitiseEditorHtml((string) ($data['html'] ?? '')),
            'explanation' => trim((string) ($data['explanation'] ?? '')),
            'scope'       => $hasSelection ? 'selection' : 'document',
        ]];
    }

    private static function editorAction(string $action, bool $hasSelection): string
    {
        $valid = ['replace_selection', 'insert_after_selection', 'append_to_document'];
        if (!in_array($action, $valid, true)) {
            $action = $hasSelection ? 'replace_selection' : 'append_to_document';
        }
        if (!$hasSelection && $action !== 'append_to_document') {
            $action = 'append_to_document';
        }
        return $action;
    }

    /**
     * Strip dangerous tags / attributes from Copilot's proposed HTML
     * before it ever reaches the browser. Whitelists only the inline /
     * block tags the editor exposes; everything else passes through
     * strip_tags. Inline event handlers and javascript: hrefs are
     * defused even on whitelisted tags.
     */
    private static function sanitiseEditorHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><ul><ol><li>'
            . '<blockquote><table><thead><tbody><tr><th><td><sup><sub><code>';
        $html = strip_tags($html, $allowed);
        $html = (string) preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#is', '', $html);
        $html = (string) preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:#i', '$1=$2', $html);
        return trim($html);
    }

    /**
     * Format a context payload from a host page into a prompt overlay
     * so the platform-wide Copilot can hold a grounded conversation
     * about the work the user is doing right now.
     *
     * Today only the collaborative article editor uses this — the
     * payload carries the article row, the live editor HTML, the
     * user's current selection and the cached critical report (when
     * one exists). The CopilotController has already verified that
     * the user owns / collaborates on the article, so by the time we
     * see the payload we can trust the fields it carries.
     *
     * Returns an empty string when there's no usable context — the
     * platform-wide system prompt stays as-is for every other page.
     *
     * @param array<string,mixed>|null $ctx
     */
    private function pageContextOverlay(?array $ctx): string
    {
        if (!is_array($ctx) || ($ctx['page'] ?? '') !== 'article-collab-editor') {
            return '';
        }
        $article = is_array($ctx['article'] ?? null) ? $ctx['article'] : [];
        $title = (string) ($article['title'] ?? 'Untitled article');
        $editorHtml = (string) ($ctx['editor_html'] ?? '');
        $selHtml = (string) ($ctx['selection_html'] ?? '');
        $selText = (string) ($ctx['selection_text'] ?? '');
        $report = is_array($ctx['report'] ?? null) ? $ctx['report'] : null;

        $overlay = "\n\n────────────────  PAGE CONTEXT  ────────────────\n"
            . "The user is on the COLLABORATIVE EDITOR for the article titled “" . $title . "”. "
            . "They have an in-page Copilot side panel that proposes Accept/Reject edits on text "
            . "selections — that flow is owned by the editor itself. As the FLOATING Copilot, your "
            . "job here is to hold a grounded conversation about the manuscript: answer questions "
            . "about its content, methodology and structure, suggest improvements in plain prose, "
            . "summarise sections, explain terms, and help the user think through revisions. "
            . "Do NOT emit JSON or proposed-edit payloads — that's the in-page panel's job. When "
            . "the user asks you to rewrite something, give them the rewritten text inline so they "
            . "can paste it themselves or move to the side panel for a tracked change.\n";

        if ($report !== null) {
            $overlay .= "\nCRITICAL REPORT FOR THIS ARTICLE (cached):\n"
                . $this->truncate(self::summariseReportForPrompt($report), 6000) . "\n";
        }

        if ($selText !== '' || $selHtml !== '') {
            $overlay .= "\nUSER'S CURRENT SELECTION INSIDE THE EDITOR";
            if ($selText !== '') {
                $overlay .= " (plain text):\n" . $this->truncate($selText, 4000);
            } elseif ($selHtml !== '') {
                $overlay .= " (HTML):\n" . $this->truncate($selHtml, 4000);
            }
            $overlay .= "\n";
        }

        $overlay .= "\nLIVE EDITOR CONTENT (HTML, possibly truncated):\n"
            . $this->truncate($editorHtml, 40000);

        return $overlay;
    }

    /**
     * Conversational mode overlays — small prompt patterns adapted from
     * imbad0202/academic-research-skills (CC-BY-NC 4.0), where they exist
     * as standalone review/research-dialogue skills. SysRevAI's Copilot is
     * a single Messages-API call per turn, not that repo's multi-agent
     * Claude Code orchestration, so these are ported as togglable system-
     * prompt overlays rather than full agent pipelines — the closest
     * equivalent that fits a one-shot chat turn. Exactly one mode is
     * active per turn; 'default' (or anything unrecognised) adds nothing.
     */
    private static function modeOverlay(string $mode): string
    {
        return match ($mode) {
            'devil_advocate' => self::devilAdvocateOverlay(),
            'socratic'       => self::socraticOverlay(),
            'fact_check'     => self::factCheckOverlay(),
            'lit_review'     => self::litReviewOverlay(),
            default          => '',
        };
    }

    /** Stress-test the user's reasoning instead of affirming it — adapted
     *  from the academic-paper-reviewer skill's Devil's Advocate pattern. */
    private static function devilAdvocateOverlay(): string
    {
        return "\n\nMODE OVERLAY — Devil's Advocate:\n"
            . " • Your job is to stress-test the user's reasoning, not affirm it. Look for weak "
            . "premises, unstated assumptions, alternative explanations and missing evidence.\n"
            . " • When the user proposes a methodological choice (screening mode, inclusion rule, "
            . "extraction approach, RoB tool…), surface the strongest counter-argument before you "
            . "agree.\n"
            . " • Push back on overconfident statements. Use phrasing like \"That assumes…\", "
            . "\"A reviewer could object that…\", \"Counter-example: …\".\n"
            . " • Concede when the user's reasoning is genuinely sound; don't be contrarian for its "
            . "own sake. Briefly state WHY you concede so the user can audit your reasoning.\n"
            . " • Stay grounded in PRISMA / Cochrane / GRADE conventions and the supplied review "
            . "context. Never invent facts to manufacture an objection.";
    }

    /** Guide the user to the answer through pointed questions instead of
     *  stating it outright — adapted from the deep-research skill's
     *  guided Socratic dialogue mode. */
    private static function socraticOverlay(): string
    {
        return "\n\nMODE OVERLAY — Mode Socràtic:\n"
            . " • Don't answer directly first. Guide the user to the answer through a short sequence "
            . "of pointed questions that make them reason it out themselves.\n"
            . " • Ask ONE focused question at a time, never a list. Wait for their answer before "
            . "moving to the next question.\n"
            . " • Build each question on what they've already said, advancing progressively toward "
            . "the conclusion.\n"
            . " • If the user seems stuck, or explicitly asks for the direct answer, give it plainly — "
            . "the goal is understanding, not obstruction.\n"
            . " • Keep the same warmth and evidence-awareness as the default mode; this changes HOW "
            . "you guide, not the standards you hold.";
    }

    /** Actively verify the user's claims against the supplied context
     *  before answering — adapted from the deep-research skill's
     *  fact-check mode and the citation-verification pattern this
     *  platform already uses in CitationVerificationService. */
    private static function factCheckOverlay(): string
    {
        return "\n\nMODE OVERLAY — Fact-check:\n"
            . " • Before answering, scan the user's last message for factual claims (numbers, "
            . "citations, methodology claims, statistics) you can check against the context you've "
            . "been given (protocol, article text, review metrics, uploaded documents).\n"
            . " • Explicitly label each claim you check as CONFIRMED (the supplied context supports "
            . "it — say where), CONTRADICTED (the context says otherwise — quote it), or UNVERIFIABLE "
            . "(nothing in your context confirms or denies it — say so plainly, don't guess).\n"
            . " • You have no live internet access in this conversation — never claim to have "
            . "verified something against a source you weren't given.\n"
            . " • After the fact-check, answer the user's actual question normally.";
    }

    /** Structure synthesis answers like a narrative literature-review
     *  paragraph instead of a flat list — adapted from the academic-paper
     *  skill's lit-review mode. */
    private static function litReviewOverlay(): string
    {
        return "\n\nMODE OVERLAY — Síntesi de literatura:\n"
            . " • When asked to summarise, compare or synthesise findings across references, write "
            . "like a narrative literature-review paragraph: group sources by theme or finding, don't "
            . "list them one by one.\n"
            . " • Explicitly surface agreements, contradictions and gaps across the sources you're "
            . "grounded in.\n"
            . " • Cite the specific source (author/year, or title if no author is available) for "
            . "every claim you attribute to it.\n"
            . " • If asked to synthesise a reference whose content you don't actually have, say so "
            . "instead of fabricating a summary for it.";
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
     * @param ?int $timeoutSeconds Per-call cURL timeout. Defaults to 120 s;
     *                             callers generating long JSON (e.g. the
     *                             article critical report) raise it so a
     *                             multi-thousand-token Opus reply doesn't
     *                             get cut mid-stream.
     * @param ?int $maxAttempts    Override MAX_RETRIES. Long generations
     *                             pass 1 — a transport timeout in that case
     *                             almost certainly means the model is still
     *                             generating, so retrying just doubles the
     *                             total wall time and pushes PHP past its
     *                             set_time_limit before any response can
     *                             be sent to the browser.
     * @return array{ok:bool,text:?string,json:mixed,error:?string}
     */
    private function request(
        string $model,
        string $system,
        array $messages,
        int $maxTokens,
        string $feature,
        ?int $reviewId,
        bool $expectJson = false,
        ?int $timeoutSeconds = null,
        ?int $maxAttempts = null
    ): array {
        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ];
        $timeout  = $timeoutSeconds ?? 120;
        $attempts = max(1, $maxAttempts ?? self::MAX_RETRIES);

        $lastError = 'request failed';
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            [$status, $raw, $transport] = $this->post($body, $timeout);

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
            if ($attempt < $attempts - 1) {
                sleep(2 ** ($attempt + 1));
            }
        }

        return ['ok' => false, 'text' => null, 'json' => null, 'error' => $lastError];
    }

    /** @return array{0:int,1:string,2:?string} */
    private function post(array $payload, int $timeoutSeconds = 120): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(30, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => 15,
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
        $decoded = json_decode($text, true);
        if ($decoded !== null) {
            return $decoded;
        }
        // Recovery: the model may have hit max_tokens mid-array, leaving
        // either an unclosed `"references": [ {...}, {...` tail or a
        // dangling object after the last well-formed one. Walk the string
        // until we have a balanced run, drop everything after the last
        // top-level "}" inside the references array, then re-close the
        // wrapping `]}` so the partial-but-still-useful set decodes.
        return $this->repairTruncatedJson($text);
    }

    /**
     * Best-effort repair for a JSON payload that was cut off by max_tokens.
     * Designed for the "references": [ {…}, {…}, {…  shape — we cannot
     * undo arbitrary truncation, but we can recover every complete object
     * before the cut, which is exactly what the import flow needs.
     */
    private function repairTruncatedJson(string $text): mixed
    {
        $len = strlen($text);
        if ($len < 2) {
            return null;
        }
        // Scan for the last balanced "}" inside a top-level array. We
        // track quote / escape state so braces inside strings don't fool
        // us. Anything after the last balanced close gets trimmed.
        $depth = 0;
        $inString = false;
        $escape = false;
        $lastSafe = -1;
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '{' || $ch === '[') {
                $depth++;
            } elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($ch === '}' && $depth === 2) {
                    // We just closed an object that lives inside the
                    // wrapping {"references":[ … ]} array. Anything past
                    // this point is unsafe to keep.
                    $lastSafe = $i;
                }
            }
        }
        if ($lastSafe < 0) {
            return null;
        }
        $trimmed = substr($text, 0, $lastSafe + 1) . "]}";
        return json_decode($trimmed, true);
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

    /**
     * Cut a pasted bibliography into chunks small enough to fit comfortably
     * inside one extraction call. We split on the boundaries that a typed
     * or pasted bibliography is most likely to expose — numbered list
     * markers ("1.", "[1]", "(1)") at the start of a line, then on
     * paragraph gaps. Pieces are reassembled into chunks under
     * $targetChars; pieces that already exceed that size on their own
     * (one giant unstructured blob) are hard-cut as a last resort.
     *
     * @return list<string>
     */
    private function splitBibliographyIntoChunks(string $text, int $targetChars = 3500): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $targetChars) {
            return [$text];
        }

        // Split before each numbered marker that follows a newline, OR on
        // any run of 2+ blank lines. Both look-behinds keep the boundary
        // marker attached to the following reference, never the previous.
        $pieces = preg_split(
            '/(?<=\n)(?=\s*(?:\d{1,3}[\.\)\]]\s|\[\d{1,3}\]\s))|\n{2,}/u',
            $text
        );
        $pieces = is_array($pieces) ? $pieces : [$text];

        $chunks = [];
        $buf    = '';
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            if ($buf === '') {
                $buf = $piece;
                continue;
            }
            if (mb_strlen($buf) + mb_strlen($piece) + 2 <= $targetChars) {
                $buf .= "\n\n" . $piece;
            } else {
                $chunks[] = $buf;
                $buf = $piece;
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }

        // Hard-cut anything that's still oversized — a wall of text with no
        // recognisable boundaries. Each slice still goes through Claude,
        // which is tolerant of mid-paragraph splits as long as we don't
        // chop a single reference in the middle.
        $hardCap = $targetChars * 2;
        $final = [];
        foreach ($chunks as $c) {
            while (mb_strlen($c) > $hardCap) {
                $final[] = mb_substr($c, 0, $hardCap);
                $c = mb_substr($c, $hardCap);
            }
            if ($c !== '') {
                $final[] = $c;
            }
        }
        return $final;
    }
}
