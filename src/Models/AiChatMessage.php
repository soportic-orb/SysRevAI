<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Per-user, per-article conversation with Claude. History is private — every
 * query is scoped by user_id so reviewers don't see each other's chats.
 */
final class AiChatMessage
{
    public static function append(int $referenceId, int $userId, string $role, string $content): void
    {
        $table = Database::table('ai_chat_history');
        Database::insert(
            "INSERT INTO `{$table}` (reference_id, user_id, role, content) VALUES (?,?,?,?)",
            [$referenceId, $userId, $role, $content]
        );
    }

    /** @return array<int,array{role:string,content:string}> */
    public static function history(int $referenceId, int $userId, int $limit = 20): array
    {
        $table = Database::table('ai_chat_history');
        $limit = max(1, min($limit, 100));
        // Newest N, but return oldest-first for prompt ordering.
        $rows = Database::select(
            "SELECT role, content FROM `{$table}`
             WHERE reference_id = ? AND user_id = ?
             ORDER BY id DESC LIMIT {$limit}",
            [$referenceId, $userId]
        );
        return array_reverse($rows);
    }
}
