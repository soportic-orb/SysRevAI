<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * In-app notifications with per-user, per-type channel preferences.
 */
final class Notification
{
    public const TYPES = [
        'invitation', 'assignment', 'conflict', 'decision_changed',
        'returned', 'comment', 'mention', 'milestone', 'weekly', 'ai_error',
    ];

    /**
     * Create a notification, honouring the recipient's in-app preference.
     * (Email delivery is wired through MailService in a later phase.)
     */
    public static function push(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $actionUrl = null,
        ?int $reviewId = null,
        ?int $referenceId = null
    ): void {
        if (!self::channelEnabled($userId, $type, 'in_app')) {
            return;
        }
        $table = Database::table('notifications');
        Database::insert(
            "INSERT INTO `{$table}`
                (user_id, type, title, message, action_url, related_review_id, related_reference_id)
             VALUES (?,?,?,?,?,?,?)",
            [$userId, $type, $title, $message, $actionUrl, $reviewId, $referenceId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        $table = Database::table('notifications');
        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function recent(int $userId, int $limit = 10): array
    {
        $table = Database::table('notifications');
        $limit = max(1, min($limit, 50));
        return Database::select(
            "SELECT * FROM `{$table}` WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function forUser(int $userId, bool $onlyUnread = false, int $limit = 50): array
    {
        $table = Database::table('notifications');
        $limit = max(1, min($limit, 200));
        $sql = "SELECT * FROM `{$table}` WHERE user_id = ?";
        if ($onlyUnread) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY id DESC LIMIT {$limit}";
        return Database::select($sql, [$userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        $table = Database::table('notifications');
        Database::affecting(
            "UPDATE `{$table}` SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        $table = Database::table('notifications');
        Database::affecting("UPDATE `{$table}` SET is_read = 1 WHERE user_id = ?", [$userId]);
    }

    /** Per-user channel preference for a type (defaults: in-app on, email off). */
    public static function channelEnabled(int $userId, string $type, string $channel): bool
    {
        $user = User::find($userId);
        $prefs = json_decode((string) ($user['notification_preferences'] ?? ''), true);
        $default = $channel === 'in_app';
        if (!is_array($prefs) || !isset($prefs[$type][$channel])) {
            return $default;
        }
        return (bool) $prefs[$type][$channel];
    }
}
