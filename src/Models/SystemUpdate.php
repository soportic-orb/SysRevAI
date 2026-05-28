<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Log of OTA platform updates pulled from GitHub.
 * Insert-only; read for the admin Maintenance page history.
 */
final class SystemUpdate
{
    public static function record(
        ?string $fromCommit,
        string $toCommit,
        ?int $triggeredBy,
        string $status,
        int $migrationsRun,
        ?string $error = null,
    ): int {
        $table = Database::table('system_updates');
        return Database::insert(
            "INSERT INTO `{$table}` (from_commit, to_commit, triggered_by, status, migrations_run, error)
             VALUES (?,?,?,?,?,?)",
            [$fromCommit, $toCommit, $triggeredBy, $status, $migrationsRun, $error]
        );
    }

    /** Latest entries (10 by default) for the maintenance panel. */
    public static function recent(int $limit = 10): array
    {
        $table = Database::table('system_updates');
        $limit = max(1, min($limit, 100));
        try {
            return Database::select("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT {$limit}");
        } catch (\Throwable) {
            return [];
        }
    }
}
