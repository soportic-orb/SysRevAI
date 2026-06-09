<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Persistent Copilot transcript. Three orthogonal scopes coexist in the
 * same table, keyed by which id column is non-NULL:
 *
 *   review_id  set, article_id NULL → review-scoped chat (the original)
 *   review_id NULL, article_id  set → article-scoped chat (Article tool)
 *   both NULL                       → global chat (platform-wide help)
 *
 * Storing server-side lets the user pick the conversation back up from
 * any device.
 */
final class CopilotMessage
{
    public static function add(?int $reviewId, int $userId, string $role, string $content, ?int $articleId = null): int
    {
        if (!in_array($role, ['user', 'assistant'], true)) {
            return 0;
        }
        $table = Database::table('copilot_messages');
        try {
            return Database::insert(
                "INSERT INTO `{$table}` (review_id, article_id, user_id, role, content) VALUES (?,?,?,?,?)",
                [$reviewId, $articleId, $userId, $role, $content]
            );
        } catch (\Throwable) {
            // article_id column missing in a partially-migrated install:
            // fall back to the old shape so review / global chats keep
            // working. Article chats will start working once the
            // migration runs.
            return Database::insert(
                "INSERT INTO `{$table}` (review_id, user_id, role, content) VALUES (?,?,?,?)",
                [$reviewId, $userId, $role, $content]
            );
        }
    }

    /**
     * Full chat history for one user in one thread, oldest first.
     *
     * @return array<int,array{role:string,content:string,created_at:string}>
     */
    public static function history(?int $reviewId, int $userId, int $limit = 200, ?int $articleId = null): array
    {
        $table = Database::table('copilot_messages');
        $limit = max(1, min($limit, 1000));
        try {
            if ($articleId !== null) {
                $rows = Database::select(
                    "SELECT role, content, created_at FROM `{$table}`
                     WHERE article_id = ? AND user_id = ?
                     ORDER BY id ASC
                     LIMIT {$limit}",
                    [$articleId, $userId]
                );
            } elseif ($reviewId === null) {
                $rows = Database::select(
                    "SELECT role, content, created_at FROM `{$table}`
                     WHERE review_id IS NULL AND (article_id IS NULL) AND user_id = ?
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

    public static function clear(?int $reviewId, int $userId, ?int $articleId = null): int
    {
        $table = Database::table('copilot_messages');
        try {
            if ($articleId !== null) {
                return Database::affecting(
                    "DELETE FROM `{$table}` WHERE article_id = ? AND user_id = ?",
                    [$articleId, $userId]
                );
            }
        } catch (\Throwable) {
            // article_id column missing → nothing to clear in that scope.
            return 0;
        }
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
