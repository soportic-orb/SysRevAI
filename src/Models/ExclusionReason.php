<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Per-review, configurable exclusion reasons.
 */
final class ExclusionReason
{
    /** @return array<int,array> */
    public static function forReview(int $reviewId): array
    {
        $table = Database::table('exclusion_reasons');
        return Database::select(
            "SELECT * FROM `{$table}` WHERE review_id = ? ORDER BY sort_order ASC, id ASC",
            [$reviewId]
        );
    }

    public static function add(int $reviewId, string $label, string $stage = 'both', int $order = 0): void
    {
        $stage = in_array($stage, ['ta', 'ft', 'both'], true) ? $stage : 'both';
        $table = Database::table('exclusion_reasons');
        Database::insert(
            "INSERT INTO `{$table}` (review_id, label, stage, sort_order) VALUES (?,?,?,?)",
            [$reviewId, $label, $stage, $order]
        );
    }

    /** Replace the whole set for a review (used by the protocol editor). */
    public static function replaceForReview(int $reviewId, array $labels, string $stage = 'both'): void
    {
        $table = Database::table('exclusion_reasons');
        Database::affecting("DELETE FROM `{$table}` WHERE review_id = ?", [$reviewId]);
        $order = 0;
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                self::add($reviewId, $label, $stage, $order++);
            }
        }
    }
}
