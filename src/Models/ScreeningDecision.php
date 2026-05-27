<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class ScreeningDecision
{
    /** Record (or update) a reviewer's own decision. */
    public static function record(
        int $referenceId,
        int $reviewerId,
        string $stage,
        string $decision,
        ?string $reason,
        ?string $notes,
        int $timeSpent = 0
    ): void {
        $table = Database::table('screening_decisions');
        Database::affecting(
            "INSERT INTO `{$table}`
                (reference_id, reviewer_id, stage, decision, reason, notes, is_resolution, time_spent_seconds)
             VALUES (?,?,?,?,?,?,0,?)
             ON DUPLICATE KEY UPDATE
                decision = VALUES(decision), reason = VALUES(reason),
                notes = VALUES(notes), time_spent_seconds = time_spent_seconds + VALUES(time_spent_seconds)",
            [$referenceId, $reviewerId, $stage, $decision, $reason, $notes, $timeSpent]
        );
    }

    /** Record a coordinator's conflict resolution. */
    public static function recordResolution(
        int $referenceId,
        int $reviewerId,
        string $stage,
        string $decision,
        ?string $notes,
        array $resolvedIds
    ): void {
        $table = Database::table('screening_decisions');
        Database::affecting(
            "INSERT INTO `{$table}`
                (reference_id, reviewer_id, stage, decision, notes, is_resolution, resolved_decisions_ids)
             VALUES (?,?,?,?,?,1,?)
             ON DUPLICATE KEY UPDATE
                decision = VALUES(decision), notes = VALUES(notes),
                resolved_decisions_ids = VALUES(resolved_decisions_ids)",
            [$referenceId, $reviewerId, $stage, $decision, $notes, json_encode($resolvedIds)]
        );
    }

    /** All non-resolution decisions for a reference, with reviewer names. */
    public static function forReference(int $referenceId, string $stage): array
    {
        $dec = Database::table('screening_decisions');
        $users = Database::table('users');
        return Database::select(
            "SELECT d.*, u.name AS reviewer_name
             FROM `{$dec}` d JOIN `{$users}` u ON u.id = d.reviewer_id
             WHERE d.reference_id = ? AND d.stage = ? AND d.is_resolution = 0
             ORDER BY d.id ASC",
            [$referenceId, $stage]
        );
    }

    public static function reviewerDecision(int $referenceId, int $reviewerId, string $stage): ?array
    {
        $table = Database::table('screening_decisions');
        return Database::selectOne(
            "SELECT * FROM `{$table}`
             WHERE reference_id = ? AND reviewer_id = ? AND stage = ? AND is_resolution = 0 LIMIT 1",
            [$referenceId, $reviewerId, $stage]
        );
    }

    public static function decidedCount(int $referenceId, string $stage): int
    {
        $table = Database::table('screening_decisions');
        $row = Database::selectOne(
            "SELECT COUNT(DISTINCT reviewer_id) AS c FROM `{$table}`
             WHERE reference_id = ? AND stage = ? AND is_resolution = 0",
            [$referenceId, $stage]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** Reviewer's progress numbers for a review/stage. */
    public static function reviewerCompleted(int $reviewId, int $reviewerId, string $stage): int
    {
        $dec = Database::table('screening_decisions');
        $refs = Database::table('references');
        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$dec}` d
             JOIN `{$refs}` r ON r.id = d.reference_id
             WHERE r.review_id = ? AND d.reviewer_id = ? AND d.stage = ? AND d.is_resolution = 0",
            [$reviewId, $reviewerId, $stage]
        );
        return (int) ($row['c'] ?? 0);
    }
}
