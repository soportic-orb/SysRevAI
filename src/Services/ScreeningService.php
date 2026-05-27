<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Core\Database;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ScreeningDecision;

/**
 * Title/abstract (and full-text) screening workflow with reviewer blinding,
 * agreement detection and conflict surfacing.
 */
final class ScreeningService
{
    /** How many independent reviewers must decide before a reference resolves. */
    public static function requiredReviewers(array $review): int
    {
        return match ($review['screening_mode']) {
            'single' => 1,
            default  => max(1, (int) $review['reviewers_required']),
        };
    }

    private static function screeningStatus(string $stage): string
    {
        return $stage === 'ft' ? 'ft_screening' : 'ta_screening';
    }

    private static function includedStatus(string $stage): string
    {
        return $stage === 'ft' ? 'ft_included' : 'ta_included';
    }

    private static function excludedStatus(string $stage): string
    {
        return $stage === 'ft' ? 'ft_excluded' : 'ta_excluded';
    }

    /** Move non-duplicate 'imported' references into the T/A screening queue. */
    public static function startScreening(int $reviewId): int
    {
        $table = Database::table('references');
        return Database::affecting(
            "UPDATE `{$table}` SET status = 'ta_screening' WHERE review_id = ? AND status = 'imported'",
            [$reviewId]
        );
    }

    /**
     * The next reference for this reviewer to screen: still open (fewer than the
     * required reviewers have decided) and not yet decided by this reviewer.
     */
    public static function nextReference(int $reviewId, int $reviewerId, array $review, string $stage = 'ta'): ?array
    {
        $refs = Database::table('references');
        $dec = Database::table('screening_decisions');
        $required = self::requiredReviewers($review);
        $status = self::screeningStatus($stage);

        return Database::selectOne(
            "SELECT r.* FROM `{$refs}` r
             WHERE r.review_id = ? AND r.status = ?
               AND (SELECT COUNT(DISTINCT d.reviewer_id) FROM `{$dec}` d
                    WHERE d.reference_id = r.id AND d.stage = ? AND d.is_resolution = 0) < ?
               AND NOT EXISTS (SELECT 1 FROM `{$dec}` d2
                    WHERE d2.reference_id = r.id AND d2.reviewer_id = ? AND d2.stage = ? AND d2.is_resolution = 0)
             ORDER BY r.id ASC LIMIT 1",
            [$reviewId, $status, $stage, $required, $reviewerId, $stage]
        );
    }

    public static function pendingForReviewer(int $reviewId, int $reviewerId, array $review, string $stage = 'ta'): int
    {
        $refs = Database::table('references');
        $dec = Database::table('screening_decisions');
        $required = self::requiredReviewers($review);
        $status = self::screeningStatus($stage);

        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$refs}` r
             WHERE r.review_id = ? AND r.status = ?
               AND (SELECT COUNT(DISTINCT d.reviewer_id) FROM `{$dec}` d
                    WHERE d.reference_id = r.id AND d.stage = ? AND d.is_resolution = 0) < ?
               AND NOT EXISTS (SELECT 1 FROM `{$dec}` d2
                    WHERE d2.reference_id = r.id AND d2.reviewer_id = ? AND d2.stage = ? AND d2.is_resolution = 0)",
            [$reviewId, $status, $stage, $required, $reviewerId, $stage]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * After a decision is recorded, finalize the reference if the required
     * reviewers agree; otherwise leave it open as a conflict.
     */
    public static function evaluate(int $referenceId, array $review, string $stage = 'ta'): void
    {
        $required = self::requiredReviewers($review);
        $decisions = ScreeningDecision::forReference($referenceId, $stage);

        $reviewers = [];
        foreach ($decisions as $d) {
            $reviewers[(int) $d['reviewer_id']] = $d['decision'];
        }
        if (count($reviewers) < $required) {
            return;
        }

        $unique = array_unique(array_values($reviewers));
        if ($unique === ['include']) {
            Reference::setStatus($referenceId, self::includedStatus($stage));
        } elseif ($unique === ['exclude']) {
            Reference::setStatus($referenceId, self::excludedStatus($stage));
        }
        // Otherwise (disagreement or any 'maybe'): stays in the screening status
        // and is surfaced as a conflict.
    }

    /** References that reached the required decisions but were not unanimous. */
    public static function conflicts(int $reviewId, array $review, string $stage = 'ta'): array
    {
        $refs = Database::table('references');
        $dec = Database::table('screening_decisions');
        $required = self::requiredReviewers($review);
        $status = self::screeningStatus($stage);

        $rows = Database::select(
            "SELECT r.* FROM `{$refs}` r
             WHERE r.review_id = ? AND r.status = ?
               AND (SELECT COUNT(DISTINCT d.reviewer_id) FROM `{$dec}` d
                    WHERE d.reference_id = r.id AND d.stage = ? AND d.is_resolution = 0) >= ?
             ORDER BY r.id ASC",
            [$reviewId, $status, $stage, $required]
        );

        $conflicts = [];
        foreach ($rows as $row) {
            $row['decisions'] = ScreeningDecision::forReference((int) $row['id'], $stage);
            $conflicts[] = $row;
        }
        return $conflicts;
    }

    public static function conflictCount(int $reviewId, array $review, string $stage = 'ta'): int
    {
        return count(self::conflicts($reviewId, $review, $stage));
    }

    public static function resolveConflict(
        int $referenceId,
        int $resolverId,
        string $stage,
        string $decision
    ): void {
        $decisions = ScreeningDecision::forReference($referenceId, $stage);
        $ids = array_map(static fn ($d) => (int) $d['id'], $decisions);

        ScreeningDecision::recordResolution($referenceId, $resolverId, $stage, $decision, null, $ids);
        Reference::setStatus(
            $referenceId,
            $decision === 'include' ? self::includedStatus($stage) : self::excludedStatus($stage)
        );
    }
}
