<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Claude API token/cost accounting.
 */
final class AiUsage
{
    public static function record(
        ?int $reviewId,
        ?int $userId,
        string $feature,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost
    ): void {
        $table = Database::table('ai_usage');
        Database::insert(
            "INSERT INTO `{$table}` (review_id, user_id, feature, model, input_tokens, output_tokens, cost_usd)
             VALUES (?,?,?,?,?,?,?)",
            [$reviewId, $userId, $feature, $model, $inputTokens, $outputTokens, $cost]
        );
    }

    /** Total estimated cost (USD) for the current calendar month. */
    public static function monthlyCost(): float
    {
        $table = Database::table('ai_usage');
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(cost_usd), 0) AS c FROM `{$table}`
             WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
        return (float) ($row['c'] ?? 0);
    }

    /** Aggregated usage for a review (tokens + cost). */
    public static function forReview(int $reviewId): array
    {
        $table = Database::table('ai_usage');
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(input_tokens),0) AS input, COALESCE(SUM(output_tokens),0) AS output,
                    COALESCE(SUM(cost_usd),0) AS cost
             FROM `{$table}` WHERE review_id = ?",
            [$reviewId]
        );
        return [
            'input_tokens'  => (int) ($row['input'] ?? 0),
            'output_tokens' => (int) ($row['output'] ?? 0),
            'cost_usd'      => (float) ($row['cost'] ?? 0),
        ];
    }
}
