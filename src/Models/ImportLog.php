<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class ImportLog
{
    public static function record(
        int $reviewId,
        ?int $userId,
        string $filename,
        string $format,
        int $parsed,
        int $imported,
        int $duplicates,
        array $errors = []
    ): void {
        $table = Database::table('import_logs');
        Database::insert(
            "INSERT INTO `{$table}`
                (review_id, user_id, filename, format, total_parsed, total_imported, total_duplicates, errors)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $reviewId, $userId, $filename, $format,
                $parsed, $imported, $duplicates,
                $errors === [] ? null : implode("\n", $errors),
            ]
        );
    }

    public static function forReview(int $reviewId, int $limit = 20): array
    {
        $table = Database::table('import_logs');
        $limit = max(1, min($limit, 100));
        return Database::select(
            "SELECT * FROM `{$table}` WHERE review_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$reviewId]
        );
    }

    /** Wipe the audit list for one review. The references themselves stay. */
    public static function clearForReview(int $reviewId): int
    {
        $table = Database::table('import_logs');
        return Database::affecting("DELETE FROM `{$table}` WHERE review_id = ?", [$reviewId]);
    }
}
