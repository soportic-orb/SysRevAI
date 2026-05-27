<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class Duplicate
{
    public static function record(
        int $reviewId,
        int $refA,
        int $refB,
        string $method,
        float $confidence,
        string $status = 'pending',
        ?string $reason = null
    ): void {
        $table = Database::table('duplicates');
        Database::insert(
            "INSERT INTO `{$table}` (review_id, ref_a_id, ref_b_id, method, confidence, status, reason)
             VALUES (?,?,?,?,?,?,?)",
            [$reviewId, $refA, $refB, $method, $confidence, $status, $reason]
        );
    }

    /** Pending candidate pairs with both references' titles, for resolution. */
    public static function pendingForReview(int $reviewId): array
    {
        $dups = Database::table('duplicates');
        $refs = Database::table('references');
        return Database::select(
            "SELECT d.*,
                    a.title AS a_title, a.year AS a_year, a.journal AS a_journal,
                    b.title AS b_title, b.year AS b_year, b.journal AS b_journal
             FROM `{$dups}` d
             JOIN `{$refs}` a ON a.id = d.ref_a_id
             JOIN `{$refs}` b ON b.id = d.ref_b_id
             WHERE d.review_id = ? AND d.status = 'pending'
             ORDER BY d.confidence DESC, d.id ASC",
            [$reviewId]
        );
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('duplicates');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
            return;
        }
        $table = Database::table('duplicates');
        Database::affecting("UPDATE `{$table}` SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function updateAi(int $id, float $confidence, string $reason): void
    {
        $table = Database::table('duplicates');
        Database::affecting(
            "UPDATE `{$table}` SET method = 'semantic', confidence = ?, reason = ? WHERE id = ?",
            [round($confidence, 3), mb_substr($reason, 0, 500), $id]
        );
    }

    public static function pendingCount(int $reviewId): int
    {
        $table = Database::table('duplicates');
        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE review_id = ? AND status = 'pending'",
            [$reviewId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
