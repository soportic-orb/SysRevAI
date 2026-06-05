<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Persistent Copilot transcript. Each row is one turn (user or assistant)
 * for a (review, user) pair when the user is inside a review, or for the
 * (NULL, user) pair when they're chatting with the global assistant from
 * outside any review. Storing server-side lets the user pick the
 * conversation back up from any device.
 */
final class CopilotMessage
{
    /** Append one turn. Pass $reviewId=null for the global thread. */
    public static function add(?int $reviewId, int $userId, string $role, string $content): int
    {
        if (!in_array($role, ['user', 'assistant'], true)) {
            return 0;
        }
        $table = Database::table('copilot_messages');
        return Database::insert(
            "INSERT INTO `{$table}` (review_id, user_id, role, content) VALUES (?,?,?,?)",
            [$reviewId, $userId, $role, $content]
        );
    }

    /**
     * Full chat history for one user in one thread, oldest first. Pass
     * $reviewId=null for the user's global thread.
     *
     * @return array<int,array{role:string,content:string,created_at:string}>
     */
    public static function history(?int $reviewId, int $userId, int $limit = 200): array
    {
        $table = Database::table('copilot_messages');
        $limit = max(1, min($limit, 1000));
        try {
            if ($reviewId === null) {
                $rows = Database::select(
                    "SELECT role, content, created_at FROM `{$table}`
                     WHERE review_id IS NULL AND user_id = ?
                     ORDER BY id ASC
                     LIMIT {$limit}",
                    [$userId]
                );
            } else {
                $rows = Database::select(
                    "SELECT role, content, created_at FROM `{$table}`
                     WHERE review_id = ? AND user_id = ?
                     ORDER BY id ASC
                     LIMIT {$limit}",
                    [$reviewId, $userId]
                );
            }
        } catch (\Throwable) {
            return [];
        }
        return array_map(static fn (array $r): array => [
            'role'       => (string) $r['role'],
            'content'    => (string) $r['content'],
            'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    /** Wipe one user's transcript in one thread. */
    public static function clear(?int $reviewId, int $userId): int
    {
        $table = Database::table('copilot_messages');
        if ($reviewId === null) {
            return Database::affecting(
                "DELETE FROM `{$table}` WHERE review_id IS NULL AND user_id = ?",
                [$userId]
            );
        }
        return Database::affecting(
            "DELETE FROM `{$table}` WHERE review_id = ? AND user_id = ?",
            [$reviewId, $userId]
        );
    }
}
