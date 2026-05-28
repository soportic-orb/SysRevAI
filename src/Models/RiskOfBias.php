<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class RiskOfBias
{
    public static function upsert(
        int $referenceId,
        int $reviewerId,
        string $tool,
        string $domain,
        ?string $judgement,
        ?string $justification,
        bool $aiSuggested = false
    ): void {
        $table = Database::table('risk_of_bias');
        Database::affecting(
            "INSERT INTO `{$table}` (reference_id, reviewer_id, tool, domain, judgement, justification, ai_suggested)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                judgement = VALUES(judgement),
                justification = VALUES(justification),
                ai_suggested = VALUES(ai_suggested)",
            [$referenceId, $reviewerId, $tool, $domain, $judgement, $justification, $aiSuggested ? 1 : 0]
        );
    }

    /** All judgements for one (reference, reviewer, tool), keyed by domain. */
    public static function forAssessment(int $referenceId, int $reviewerId, string $tool): array
    {
        $table = Database::table('risk_of_bias');
        $rows = Database::select(
            "SELECT * FROM `{$table}`
             WHERE reference_id = ? AND reviewer_id = ? AND tool = ?",
            [$referenceId, $reviewerId, $tool]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['domain']] = $row;
        }
        return $map;
    }

    /** Aggregated traffic-light data for a whole review with a given tool. */
    public static function forReviewTool(int $reviewId, string $tool): array
    {
        $rob = Database::table('risk_of_bias');
        $refs = Database::table('references');
        return Database::select(
            "SELECT r.id AS reference_id, r.title, b.reviewer_id, b.domain, b.judgement
             FROM `{$refs}` r
             LEFT JOIN `{$rob}` b ON b.reference_id = r.id AND b.tool = ?
             WHERE r.review_id = ? AND r.status IN ('extracted','ft_included')
             ORDER BY r.id ASC",
            [$tool, $reviewId]
        );
    }
}
