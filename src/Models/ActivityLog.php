<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;

/**
 * Append-only audit trail. Never pass sensitive values (API keys, passwords)
 * in $details — log the key name and the action, not the secret.
 */
final class ActivityLog
{
    public static function record(string $action, array $details = [], ?int $reviewId = null): void
    {
        $table = Database::table('activity_log');
        Database::insert(
            "INSERT INTO `{$table}` (user_id, review_id, action, details_json, ip_address)
             VALUES (?,?,?,?,?)",
            [
                Auth::id(),
                $reviewId,
                $action,
                $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    /** Most recent entries, for the admin maintenance/system widget. */
    public static function recent(int $limit = 10): array
    {
        $table = Database::table('activity_log');
        $limit = max(1, min($limit, 200));
        return Database::select("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT {$limit}");
    }
}
