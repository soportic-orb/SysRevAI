<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Per-database search-strategy syntax recorded on the "Sintaxis de
 * recerca" page. Rows are written wholesale: a save() wipes every
 * row for the review and re-inserts what the page submitted.
 */
final class ReviewSearchSyntax
{
    /**
     * @return array<int,array{id:int,database_key:string,syntax:string,sort_order:int}>
     */
    public static function listForReview(int $reviewId): array
    {
        $table = Database::table('review_search_syntaxes');
        try {
            $rows = Database::select(
                "SELECT id, database_key, syntax, sort_order
                   FROM `{$table}` WHERE review_id = ?
               ORDER BY sort_order, id",
                [$reviewId]
            );
        } catch (\Throwable) {
            return [];
        }
        return array_map(static fn (array $r): array => [
            'id'           => (int) $r['id'],
            'database_key' => (string) $r['database_key'],
            'syntax'       => (string) $r['syntax'],
            'sort_order'   => (int) $r['sort_order'],
        ], $rows);
    }

    /**
     * Replace every row for the review with the supplied list. Each
     * input item must be {database_key, syntax}. Rows with an empty
     * syntax are dropped to keep the table tidy.
     *
     * @param array<int,array{database_key:string,syntax:string}> $rows
     */
    public static function replaceAll(int $reviewId, array $rows, ?int $userId): void
    {
        $table = Database::table('review_search_syntaxes');
        Database::pdo()->beginTransaction();
        try {
            Database::affecting("DELETE FROM `{$table}` WHERE review_id = ?", [$reviewId]);
            $order = 0;
            foreach ($rows as $row) {
                $key    = (string) ($row['database_key'] ?? '');
                $syntax = trim((string) ($row['syntax'] ?? ''));
                if ($syntax === '') {
                    continue;
                }
                Database::affecting(
                    "INSERT INTO `{$table}`
                        (review_id, database_key, syntax, sort_order, updated_by)
                     VALUES (?, ?, ?, ?, ?)",
                    [$reviewId, $key, mb_substr($syntax, 0, 16000), $order++, $userId]
                );
            }
            Database::pdo()->commit();
        } catch (\Throwable $e) {
            Database::pdo()->rollBack();
            throw $e;
        }
    }
}
