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
    public static function add(?int $reviewId, int $userId, string $role, string $content, ?int $articleId = null, ?array $pendingAction = null): int
    {
        if (!in_array($role, ['user', 'assistant'], true)) {
            return 0;
        }
        $table = Database::table('copilot_messages');
        $actionJson = $pendingAction !== null ? json_encode($pendingAction, JSON_UNESCAPED_UNICODE) : null;
        $actionStatus = $pendingAction !== null ? 'pending' : null;
        try {
            return Database::insert(
                "INSERT INTO `{$table}` (review_id, article_id, user_id, role, content, pending_action, pending_action_status)
                 VALUES (?,?,?,?,?,?,?)",
                [$reviewId, $articleId, $userId, $role, $content, $actionJson, $actionStatus]
            );
        } catch (\Throwable) {
            try {
                // pending_action* columns missing (mig. 038 not run yet)
                return Database::insert(
                    "INSERT INTO `{$table}` (review_id, article_id, user_id, role, content) VALUES (?,?,?,?,?)",
                    [$reviewId, $articleId, $userId, $role, $content]
                );
            } catch (\Throwable) {
                // article_id column missing too — fall back to the
                // oldest shape so review / global chats keep working.
                return Database::insert(
                    "INSERT INTO `{$table}` (review_id, user_id, role, content) VALUES (?,?,?,?)",
                    [$reviewId, $userId, $role, $content]
                );
            }
        }
    }

    /**
     * Fetch a single message row including the action proposal fields,
     * if the migration has run. Used by the confirm endpoint to read
     * the proposal back from the DB instead of trusting the client.
     *
     * @return array<string,mixed>|null
     */
    public static function find(int $id): ?array
    {
        $table = Database::table('copilot_messages');
        try {
            return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Update the pending_action_status + pending_action_result of an
     * assistant turn after the user has acted on the proposal.
     */
    public static function updateActionStatus(int $messageId, string $status, ?array $result = null): void
    {
        if (!in_array($status, ['pending', 'accepted', 'executed', 'rejected', 'failed'], true)) {
            return;
        }
        $table = Database::table('copilot_messages');
        $resultJson = $result !== null ? json_encode($result, JSON_UNESCAPED_UNICODE) : null;
        try {
            Database::affecting(
                "UPDATE `{$table}`
                    SET pending_action_status = ?, pending_action_result = ?
                  WHERE id = ?",
                [$status, $resultJson, $messageId]
            );
        } catch (\Throwable) {
            // pre-migration install — silent no-op.
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
        // Try the new column set first; on a pre-migration install
        // (no pending_action* columns) fall back to the legacy SELECT
        // so existing chat history keeps loading. Same for the
        // pre-029 install without article_id.
        $selects = [
            'id, role, content, created_at, pending_action, pending_action_status, pending_action_result',
            'id, role, content, created_at',
        ];
        $rows = [];
        foreach ($selects as $selectList) {
            try {
                if ($articleId !== null) {
                    $rows = Database::select(
                        "SELECT {$selectList} FROM `{$table}`
                         WHERE article_id = ? AND user_id = ?
                         ORDER BY id ASC
                         LIMIT {$limit}",
                        [$articleId, $userId]
                    );
                } elseif ($reviewId === null) {
                    $rows = Database::select(
                        "SELECT {$selectList} FROM `{$table}`
                         WHERE review_id IS NULL AND (article_id IS NULL) AND user_id = ?
                         ORDER BY id ASC
                         LIMIT {$limit}",
                        [$userId]
                    );
                } else {
                    $rows = Database::select(
                        "SELECT {$selectList} FROM `{$table}`
                         WHERE review_id = ? AND user_id = ?
                         ORDER BY id ASC
                         LIMIT {$limit}",
                        [$reviewId, $userId]
                    );
                }
                break; // SELECT succeeded
            } catch (\Throwable) {
                continue; // try the simpler SELECT
            }
        }
        return array_map(static function (array $r): array {
            // pending_action* columns are only on rows persisted after
            // migration 038. Missing keys safely fall back to null so
            // the front end always sees a uniform shape.
            $action  = $r['pending_action']        ?? null;
            $status  = $r['pending_action_status'] ?? null;
            $result  = $r['pending_action_result'] ?? null;
            $decode  = static function (?string $j) {
                if ($j === null || $j === '') return null;
                $d = json_decode($j, true);
                return is_array($d) ? $d : null;
            };
            return [
                'id'         => (int) ($r['id'] ?? 0),
                'role'       => (string) $r['role'],
                'content'    => (string) $r['content'],
                'created_at' => (string) $r['created_at'],
                'action'     => $decode(is_string($action) ? $action : null),
                'action_status' => is_string($status) ? $status : null,
                'action_result' => $decode(is_string($result) ? $result : null),
            ];
        }, $rows);
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
