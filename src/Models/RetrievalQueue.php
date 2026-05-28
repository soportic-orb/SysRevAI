<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Background-processing queue for full-text retrieval. The worker (added in
 * a later sub-phase) drains pending jobs in priority order.
 */
final class RetrievalQueue
{
    public static function enqueue(int $referenceId, ?int $userId, int $priority = 5): int
    {
        $table = Database::table('retrieval_queue');
        return Database::insert(
            "INSERT INTO `{$table}` (reference_id, priority, status, requested_by)
             VALUES (?,?, 'pending', ?)",
            [$referenceId, max(0, min(9, $priority)), $userId]
        );
    }

    /** Pending jobs ordered by priority then age. */
    public static function pending(int $limit = 20): array
    {
        $table = Database::table('retrieval_queue');
        $limit = max(1, min($limit, 200));
        return Database::select(
            "SELECT * FROM `{$table}` WHERE status = 'pending'
             ORDER BY priority ASC, id ASC LIMIT {$limit}"
        );
    }

    public static function markProcessing(int $id): void
    {
        $table = Database::table('retrieval_queue');
        Database::affecting(
            "UPDATE `{$table}` SET status = 'processing', started_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function markCompleted(int $id): void
    {
        $table = Database::table('retrieval_queue');
        Database::affecting(
            "UPDATE `{$table}` SET status = 'completed', completed_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function markFailed(int $id, string $error): void
    {
        $table = Database::table('retrieval_queue');
        Database::affecting(
            "UPDATE `{$table}` SET status = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?",
            [mb_substr($error, 0, 1000), $id]
        );
    }

    /** @return array{pending:int,processing:int,completed:int,failed:int} */
    public static function summary(): array
    {
        $table = Database::table('retrieval_queue');
        $rows = Database::select("SELECT status, COUNT(*) AS c FROM `{$table}` GROUP BY status");
        $out = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['c'];
        }
        return $out;
    }
}
