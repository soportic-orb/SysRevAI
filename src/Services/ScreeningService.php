<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Core\Database;
use SysRevAI\Models\Notification;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReviewUser;
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

    /**
     * Every status a reference passes through once it has entered this
     * stage's pipeline (still screening, or already finalized either way).
     * Used to validate the prev/next navigation and the history reopen —
     * both let a reviewer land on a reference outside the normal "next
     * pending" queue, but only within this stage, never on an 'imported'
     * or 'duplicate' row that hasn't reached it yet.
     *
     * @return string[]
     */
    public static function stageStatuses(string $stage = 'ta'): array
    {
        return [self::screeningStatus($stage), self::includedStatus($stage), self::excludedStatus($stage)];
    }

    /**
     * Promote references into a screening queue:
     *   stage = 'ta' → 'imported' becomes 'ta_screening'
     *   stage = 'ft' → 'ta_included' becomes 'ft_screening'
     */
    public static function startScreening(int $reviewId, string $stage = 'ta'): int
    {
        $table = Database::table('references');
        if ($stage === 'ft') {
            return Database::affecting(
                "UPDATE `{$table}` SET status = 'ft_screening' WHERE review_id = ? AND status = 'ta_included'",
                [$reviewId]
            );
        }
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

    /**
     * Same as nextReference(), but skips past $afterId — used by the "next
     * to screen" nav arrow so clicking it from the reference currently on
     * screen (which is itself usually the smallest pending id) actually
     * advances instead of reloading the same one.
     */
    public static function nextReferenceAfter(int $reviewId, int $afterId, int $reviewerId, array $review, string $stage = 'ta'): ?array
    {
        $refs = Database::table('references');
        $dec = Database::table('screening_decisions');
        $required = self::requiredReviewers($review);
        $status = self::screeningStatus($stage);

        return Database::selectOne(
            "SELECT r.* FROM `{$refs}` r
             WHERE r.review_id = ? AND r.status = ? AND r.id > ?
               AND (SELECT COUNT(DISTINCT d.reviewer_id) FROM `{$dec}` d
                    WHERE d.reference_id = r.id AND d.stage = ? AND d.is_resolution = 0) < ?
               AND NOT EXISTS (SELECT 1 FROM `{$dec}` d2
                    WHERE d2.reference_id = r.id AND d2.reviewer_id = ? AND d2.stage = ? AND d2.is_resolution = 0)
             ORDER BY r.id ASC LIMIT 1",
            [$reviewId, $status, $afterId, $stage, $required, $reviewerId, $stage]
        );
    }

    /**
     * The reference right before $beforeId that has entered this stage's
     * pipeline, regardless of whether it's still pending or already
     * decided — the "previous" nav arrow deliberately lets a reviewer step
     * back through anything already screened, not just their own history.
     */
    public static function previousReference(int $reviewId, int $beforeId, string $stage = 'ta'): ?array
    {
        $refs = Database::table('references');
        $statuses = self::stageStatuses($stage);

        return Database::selectOne(
            "SELECT r.* FROM `{$refs}` r
             WHERE r.review_id = ? AND r.id < ? AND r.status IN (?, ?, ?)
             ORDER BY r.id DESC LIMIT 1",
            array_merge([$reviewId, $beforeId], $statuses)
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

    /** All references in the review, regardless of stage / status. Used as the
     *  denominator for the per-reviewer screening stats. */
    public static function totalReferences(int $reviewId): int
    {
        $refs = Database::table('references');
        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$refs}` WHERE review_id = ?",
            [$reviewId]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** Total references at the screening stage (still open for any reviewer
     *  to decide), counted from anyone's perspective — independent of who
     *  is currently logged in. */
    public static function totalInStage(int $reviewId, string $stage = 'ta'): int
    {
        $refs = Database::table('references');
        $row = Database::selectOne(
            "SELECT COUNT(*) AS c FROM `{$refs}` WHERE review_id = ? AND status = ?",
            [$reviewId, self::screeningStatus($stage)]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Whether a reviewer is still allowed to submit (or edit) a decision for
     * this reference right now.
     *
     * Editing a decision the reviewer already made is always allowed,
     * regardless of the reference's current status — that's what lets the
     * "referències revisades" history reopen an already-finalized reference
     * for correction; evaluate() re-runs afterwards and will flip the
     * status again if the edit changes the outcome (e.g. into a conflict).
     *
     * A brand-new decision requires the reference to still be open for
     * this stage and the reviewers_required quota to not yet be reached —
     * the server-side backstop for nextReference()'s queue filtering,
     * guarding against a reviewer who already had the reference loaded
     * (stale page) or a direct POST with a tampered reference_id from
     * pushing the decision count past the configured quota.
     */
    public static function canDecide(array $reference, int $reviewerId, array $review, string $stage = 'ta'): bool
    {
        if (ScreeningDecision::reviewerDecision((int) $reference['id'], $reviewerId, $stage) !== null) {
            return true;
        }
        if ((string) $reference['status'] !== self::screeningStatus($stage)) {
            return false;
        }
        return ScreeningDecision::decidedCount((int) $reference['id'], $stage) < self::requiredReviewers($review);
    }

    /**
     * Record a reviewer's decision and evaluate the outcome in one step —
     * shared by the single-reference decide() flow and the references-list
     * bulk-screen action so both apply the exact same required-reviewers
     * bookkeeping.
     *
     * @return bool True if this decision just completed the
     *              reviewers_required quota without a unanimous result —
     *              i.e. a brand new conflict the caller should notify
     *              resolvers about.
     */
    public static function recordDecision(
        array $review,
        int $referenceId,
        int $reviewerId,
        string $stage,
        string $decision,
        ?string $reason,
        ?string $notes,
        int $timeSpent = 0,
        ?string $aiSuggestionJson = null
    ): bool {
        $required = self::requiredReviewers($review);
        $before = ScreeningDecision::decidedCount($referenceId, $stage);

        ScreeningDecision::record($referenceId, $reviewerId, $stage, $decision, $reason, $notes, $timeSpent, $aiSuggestionJson);
        self::evaluate($referenceId, $review, $stage);

        $after = ScreeningDecision::decidedCount($referenceId, $stage);
        $fresh = Reference::find($referenceId);
        return $before < $required && $after >= $required
            && $fresh !== null && (string) $fresh['status'] === self::screeningStatus($stage);
    }

    /** Notify every resolver (owner/admin/can_resolve_conflicts) except the
     *  deciding reviewer that a reference just became a conflict. */
    public static function notifyConflictResolvers(array $review, int $reviewId, int $exceptUserId, string $basePath): void
    {
        foreach (ReviewUser::resolverIds($reviewId, (int) $review['owner_id']) as $resolverId) {
            if ($resolverId === $exceptUserId) {
                continue;
            }
            Notification::push(
                $resolverId,
                'conflict',
                __('screening.notif_conflict', $review['title']),
                null,
                $basePath . '/conflicts',
                $reviewId
            );
        }
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
