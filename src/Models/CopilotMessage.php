<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Persistent transcript of a researcher's Scientific Copilot conversation
 * inside one review. Scoped per (review_id, user_id) so the bot remembers
 * what was said to whom — and the user can come back from another device.
 */
final class CopilotMessage
{
    /** Append one turn (user or assistant) to the thread. */
    public static function add(int $reviewId, int $userId, string $role, string $content): int
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
     * Full chat history for one researcher in one review, oldest first.
     *
     * @return array<int,array{role:string,content:string,created_at:string}>
     */
    public static function history(int $reviewId, int $userId, int $limit = 200): array
    {
        $table = Database::table('copilot_messages');
        $limit = max(1, min($limit, 1000));
        try {
            $rows = Database::select(
                "SELECT role, content, created_at FROM `{$table}`
                 WHERE review_id = ? AND user_id = ?
                 ORDER BY id ASC
                 LIMIT {$limit}",
                [$reviewId, $userId]
            );
        } catch (\Throwable) {
            return [];
        }
        return array_map(static fn (array $r): array => [
            'role'       => (string) $r['role'],
            'content'    => (string) $r['content'],
            'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    /** Wipe one researcher's transcript in one review. */
    public static function clear(int $reviewId, int $userId): int
    {
        $table = Database::table('copilot_messages');
        return Database::affecting(
            "DELETE FROM `{$table}` WHERE review_id = ? AND user_id = ?",
            [$reviewId, $userId]
        );
    }
}
