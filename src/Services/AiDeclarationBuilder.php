<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\AiUsage;

/**
 * AI-usage declaration builder.
 *
 * Reads the ai_usage log for a review and groups the Claude calls by
 * feature into a human-readable report the researcher can attach to a
 * publication (PRISMA / Cochrane / journal-policy AI-disclosure
 * requirements increasingly ask for this).
 *
 * The labels and narrative templates live under the
 * "ai_declaration.features.*" translation namespace so the report
 * reads naturally in the user's locale.
 */
final class AiDeclarationBuilder
{
    /**
     * Map of internal feature key → translation suffix. Keys come from
     * ClaudeService's `feature` argument to request() (and from
     * legacy event names). Anything that isn't listed here gets a
     * generic "other" label — better than dropping the call from the
     * report.
     *
     * @return array<string,string>
     */
    public static function knownFeatures(): array
    {
        return [
            'protocol'    => 'protocol',    // legacy alias
            'extraction'  => 'extraction',  // data-extraction calls
            'screening'   => 'screening',   // include / exclude / maybe suggestion
            'bias'        => 'bias',        // risk-of-bias domain judgement
            'peer_review' => 'peer_review', // peer-review rubric / critical report
            'summaries'   => 'summaries',   // article summary
            'copilot'     => 'copilot',     // copilot conversational assistant
            'chat'        => 'chat',        // legacy alias for copilot/article chat
            'content'     => 'content',     // miscellaneous content generation
            'dedup'       => 'dedup',       // duplicate-pair semantic check
            'verify'      => 'verify',      // citation-verification
        ];
    }

    /**
     * Build the report structure: one entry per feature that actually
     * shows up in the log for the review. Each entry carries enough
     * data to render both the on-screen table and the Word export.
     *
     * @return array{
     *   review: array<string,mixed>,
     *   totals: array{calls:int,input_tokens:int,output_tokens:int,cost_usd:float},
     *   features: array<int,array{
     *     key:string, label:string, description:string,
     *     calls:int, models:array<int,string>,
     *     first_at:?string, last_at:?string,
     *     input_tokens:int, output_tokens:int
     *   }>,
     *   period: array{first_at:?string,last_at:?string}
     * }
     */
    public static function build(array $review): array
    {
        $rid = (int) ($review['id'] ?? 0);
        $rows = AiUsage::listForReview($rid, 1000);
        $known = self::knownFeatures();

        $buckets = [];
        $totalCalls = 0;
        $totalIn = 0;
        $totalOut = 0;
        $totalCost = 0.0;
        $firstAt = null;
        $lastAt  = null;

        foreach ($rows as $r) {
            $featureRaw = (string) ($r['feature'] ?? 'other');
            // Hide platform-internal calls that have no scientific
            // relevance to the user's research (connectivity ping,
            // setup verification). They'd just confuse the reader of
            // the declaration document.
            if ($featureRaw === 'ping') {
                continue;
            }
            $key = $known[$featureRaw] ?? 'other';
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'key'           => $key,
                    'label'         => __('ai_declaration.features.' . $key . '.label'),
                    'description'   => __('ai_declaration.features.' . $key . '.description'),
                    'calls'         => 0,
                    'models'        => [],
                    'first_at'      => null,
                    'last_at'       => null,
                    'input_tokens'  => 0,
                    'output_tokens' => 0,
                ];
            }
            $buckets[$key]['calls']++;
            $in  = (int) ($r['input_tokens']  ?? 0);
            $out = (int) ($r['output_tokens'] ?? 0);
            $buckets[$key]['input_tokens']  += $in;
            $buckets[$key]['output_tokens'] += $out;
            $totalCalls++;
            $totalIn += $in;
            $totalOut += $out;
            $totalCost += (float) ($r['cost_usd'] ?? 0);
            $createdAt = (string) ($r['created_at'] ?? '');
            if ($createdAt !== '') {
                if ($buckets[$key]['first_at'] === null || $createdAt < $buckets[$key]['first_at']) {
                    $buckets[$key]['first_at'] = $createdAt;
                }
                if ($buckets[$key]['last_at'] === null || $createdAt > $buckets[$key]['last_at']) {
                    $buckets[$key]['last_at'] = $createdAt;
                }
                if ($firstAt === null || $createdAt < $firstAt) {
                    $firstAt = $createdAt;
                }
                if ($lastAt === null || $createdAt > $lastAt) {
                    $lastAt = $createdAt;
                }
            }
            $model = (string) ($r['model'] ?? '');
            if ($model !== '' && !in_array($model, $buckets[$key]['models'], true)) {
                $buckets[$key]['models'][] = $model;
            }
        }

        // Stable order: feature key sequence we know about first, the
        // unknown "other" bucket last.
        $ordered = [];
        foreach ($known as $orig => $key) {
            if (isset($buckets[$key]) && !isset($ordered[$key])) {
                $ordered[$key] = $buckets[$key];
            }
        }
        if (isset($buckets['other'])) {
            $ordered['other'] = $buckets['other'];
        }

        return [
            'review'  => $review,
            'totals'  => [
                'calls'         => $totalCalls,
                'input_tokens'  => $totalIn,
                'output_tokens' => $totalOut,
                'cost_usd'      => $totalCost,
            ],
            'features' => array_values($ordered),
            'period'   => ['first_at' => $firstAt, 'last_at' => $lastAt],
        ];
    }
}
