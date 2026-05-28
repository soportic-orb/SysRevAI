<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

use SysRevAI\Core\Database;

/**
 * Per-source request budget enforced through a single `rate_limit_counters`
 * row updated atomically with INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * The contract is intentionally narrow: `acquire()` returns true (and bumps
 * the counter) when a call is allowed, false when the source has spent its
 * budget for the current window. `BaseHttpSource` integrates the check so
 * existing source classes need no changes.
 *
 * In dev / pre-install (no DB), the limiter degrades to "always allow" so
 * source classes still work in isolation.
 */
final class RateLimiter
{
    /**
     * @param array{requests:int,per_seconds:int} $limit
     */
    public static function acquire(string $source, array $limit): bool
    {
        $max     = max(1, (int) ($limit['requests'] ?? 1));
        $windowS = max(1, (int) ($limit['per_seconds'] ?? 1));

        try {
            $table = Database::table('rate_limit_counters');

            // Reset the counter if the previous window has expired (atomic).
            Database::affecting(
                "INSERT INTO `{$table}` (source, window_start, count) VALUES (?, NOW(), 0)
                 ON DUPLICATE KEY UPDATE
                    window_start = IF(window_start < (NOW() - INTERVAL ? SECOND), NOW(), window_start),
                    count        = IF(window_start < (NOW() - INTERVAL ? SECOND), 0, count)",
                [$source, $windowS, $windowS]
            );

            $row = Database::selectOne(
                "SELECT count FROM `{$table}` WHERE source = ? LIMIT 1",
                [$source]
            );
            if ((int) ($row['count'] ?? 0) >= $max) {
                return false;
            }

            Database::affecting(
                "UPDATE `{$table}` SET count = count + 1 WHERE source = ?",
                [$source]
            );
            return true;
        } catch (\Throwable) {
            // No DB available — fail open so unit tests + pre-install work.
            return true;
        }
    }

    /** Currently-consumed count for a source in its active window. */
    public static function used(string $source): int
    {
        try {
            $table = Database::table('rate_limit_counters');
            $row = Database::selectOne(
                "SELECT count FROM `{$table}` WHERE source = ? LIMIT 1",
                [$source]
            );
            return (int) ($row['count'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
