<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Duplicate;
use SysRevAI\Models\ExclusionReason;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Models\ScreeningDecision;
use SysRevAI\Services\DeduplicationService;
use SysRevAI\Services\FileStorage;
use SysRevAI\Services\ScreeningService;

final class ReferencesController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $status   = (string) ($_GET['status'] ?? '');
        $search   = trim((string) ($_GET['q'] ?? ''));
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $abstract = in_array(($_GET['abstract'] ?? ''), ['with', 'without'], true)
            ? (string) $_GET['abstract']
            : '';
        // Source filter — populated from the distinct source_file values
        // recorded on this review's references. We validate the GET value
        // against that list so a forged query string can't reveal whether
        // a foreign source name exists in another review.
        $sourceOptions = Reference::distinctSources($rid);
        $source = (string) ($_GET['source'] ?? '');
        if ($source !== '' && !in_array($source, $sourceOptions, true)) {
            $source = '';
        }
        // Per-page selector with a whitelisted set so a forged query
        // string can't ask for an unbounded LIMIT. 100 is the default
        // — fits most screens without paging through five tabs to
        // find a known reference.
        $perPageOptions = [50, 100, 200, 500, 1000];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }

        $pendingStage = $this->pendingStageFor($status);
        if ($pendingStage !== null) {
            $result = ScreeningService::pendingForReviewerList(
                $rid,
                (int) Auth::id(),
                $review,
                $pendingStage,
                $search,
                $abstract,
                $source,
                $page,
                $perPage
            );
        } else {
            $result = Reference::forReview($rid, $status, $search, $page, $perPage, $abstract, $source);
        }

        echo View::render('references/index', [
            'review'        => $review,
            'rows'          => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page,
            'perPage'       => $perPage,
            'perPageOptions' => $perPageOptions,
            'status'        => $status,
            'search'        => $search,
            'abstract'      => $abstract,
            'source'        => $source,
            'sourceOptions' => $sourceOptions,
            'statuses'      => Reference::STATUSES,
            'metrics'       => Review::metrics($rid),
            'pendingDups'   => Duplicate::pendingCount($rid),
            'ftStatus'      => \SysRevAI\Models\ReferenceFullTextStatus::mapForReview($rid),
            'ftInFlight'    => \SysRevAI\Models\RetrievalQueue::inFlightForReview($rid),
            'ftEnabled'     => (bool) (setting('fulltext.enabled') ?? false),
            'canDelete'     => $this->canDelete($review),
            'reasons'       => ExclusionReason::forReview($rid),
        ]);
    }

    /**
     * Delete an imported reference. Allowed only while the reference
     * has not yet entered any reviewer's decision history — once anyone
     * has screened it the row is part of the audit trail and must stay.
     * Cascading FKs handle related rows; the on-disk PDF (if any) is
     * unlinked explicitly because it lives outside the database.
     */
    public function delete(string $id, string $refId): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $referenceId = (int) $refId;

        if (!$this->canDelete($review)) {
            Session::flash('error', __('references.delete_forbidden'));
            redirect('/reviews/' . $rid . '/references');
        }

        $ref = Reference::find($referenceId);
        if ($ref === null || (int) $ref['review_id'] !== $rid) {
            Session::flash('error', __('references.delete_not_found'));
            redirect('/reviews/' . $rid . '/references');
        }

        if (ScreeningDecision::hasAnyForReference($referenceId)) {
            Session::flash('error', __('references.delete_blocked'));
            redirect('/reviews/' . $rid . '/references');
        }

        // Unlink the PDF (if any) before the DB cascade removes the row.
        $ft = ReferenceFullText::find($referenceId);
        if ($ft !== null && !empty($ft['pdf_path'])) {
            FileStorage::delete((string) $ft['pdf_path']);
        }

        $wasDuplicate = (string) ($ref['status'] ?? '') === 'duplicate';
        Reference::delete($referenceId);
        if ($wasDuplicate) {
            Review::addDuplicatesRemoved($rid, 1);
        }

        ActivityLog::record('references.deleted', [
            'reference_id'      => $referenceId,
            'title'             => (string) ($ref['title'] ?? ''),
            'was_duplicate'     => $wasDuplicate,
        ], $rid);

        Session::flash('success', __('references.delete_ok'));
        redirect('/reviews/' . $rid . '/references');
    }

    /** Owner or platform admin only — destructive action on shared data. */
    private function canDelete(array $review): bool
    {
        $uid = (int) Auth::id();
        return (int) $review['owner_id'] === $uid || Auth::hasRole('owner', 'admin');
    }

    /**
     * Maps the references-list status filter's two pseudo-status options
     * ("Cribatge T/R pendent" / "Cribatge TextComp pendent") to a
     * screening stage — null for a real Reference::STATUSES value (or no
     * filter), which the caller handles with the normal status query.
     */
    private function pendingStageFor(string $status): ?string
    {
        return match ($status) {
            'pending_ta' => 'ta',
            'pending_ft' => 'ft',
            default      => null,
        };
    }

    /**
     * Bulk delete. Accepts two scopes posted from the references page:
     *
     *   - `scope=filtered` (with the current status/q hidden inputs) —
     *     wipe every reference matching the filter the user is looking
     *     at. Enables the "delete all" toolbar action without paginating.
     *   - `scope=ids` + `reference_ids[]` — wipe a specific list. Used by
     *     the per-page "select selected" flow and by the bulk-delete
     *     button on the Duplicates page.
     *
     * Rows that have already accrued screening decisions are skipped
     * (counted), never deleted — destroying them would leave orphaned
     * decisions and break the audit trail. The on-disk PDF for each
     * deleted row is unlinked here because the DB cascade can't reach
     * the filesystem.
     */
    public function deleteBulk(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        if (!$this->canDelete($review)) {
            Session::flash('error', __('references.delete_forbidden'));
            redirect('/reviews/' . $rid . '/references');
        }

        $scope = (string) ($_POST['scope'] ?? '');
        if ($scope === 'filtered') {
            $status   = (string) ($_POST['status'] ?? '');
            $search   = trim((string) ($_POST['q'] ?? ''));
            $abstract = in_array(($_POST['abstract'] ?? ''), ['with', 'without'], true)
                ? (string) $_POST['abstract']
                : '';
            $source   = trim((string) ($_POST['source'] ?? ''));
            $pendingStage = $this->pendingStageFor($status);
            $ids = $pendingStage !== null
                ? ScreeningService::pendingReferenceIds($rid, (int) Auth::id(), $review, $pendingStage, $search, $abstract, $source)
                : Reference::idsForReview($rid, $status, $search, $abstract, $source);
        } else {
            $raw = (array) ($_POST['reference_ids'] ?? []);
            $ids = array_values(array_unique(array_map('intval', $raw)));
            $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));
        }

        $back = (string) ($_POST['back'] ?? '/reviews/' . $rid . '/references');
        // Cap the back URL to in-app destinations so a forged POST can't
        // redirect to an attacker-controlled site after the bulk op.
        if (!str_starts_with($back, '/')) {
            $back = '/reviews/' . $rid . '/references';
        }

        if ($ids === []) {
            Session::flash('error', __('references.delete_bulk_none'));
            redirect($back);
        }

        @set_time_limit(180);
        $deleted = 0;
        $locked  = 0;
        $errors  = 0;
        // Track how many of the deleted rows were duplicates so the
        // PRISMA "Duplicates removed" cell can keep reflecting them
        // after the rows themselves are gone.
        $duplicatesRemoved = 0;
        foreach ($ids as $refId) {
            $ref = Reference::find((int) $refId);
            if ($ref === null || (int) $ref['review_id'] !== $rid) {
                continue;
            }
            if (ScreeningDecision::hasAnyForReference((int) $refId)) {
                $locked++;
                continue;
            }
            try {
                $ft = ReferenceFullText::find((int) $refId);
                if ($ft !== null && !empty($ft['pdf_path'])) {
                    FileStorage::delete((string) $ft['pdf_path']);
                }
                $wasDuplicate = (string) ($ref['status'] ?? '') === 'duplicate';
                Reference::delete((int) $refId);
                $deleted++;
                if ($wasDuplicate) {
                    $duplicatesRemoved++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }
        Review::addDuplicatesRemoved($rid, $duplicatesRemoved);

        ActivityLog::record('references.deleted_bulk', [
            'scope'              => $scope === 'filtered' ? 'filtered' : 'ids',
            'deleted'            => $deleted,
            'locked'             => $locked,
            'errors'             => $errors,
            'duplicates_removed' => $duplicatesRemoved,
        ], $rid);

        if ($deleted === 0 && $locked > 0) {
            Session::flash('error', __('references.delete_bulk_all_locked', $locked));
        } elseif ($locked > 0 || $errors > 0) {
            Session::flash('success', __('references.delete_bulk_partial', $deleted, $locked, $errors));
        } else {
            Session::flash('success', __('references.delete_bulk_ok', $deleted));
        }
        redirect($back);
    }

    /**
     * Bulk-screen selected references straight from the list: lets a
     * reviewer search or page through, pick specific rows, and record the
     * same T/A decision (include/maybe/exclude, with an optional reason
     * and notes) for all of them in one go — instead of waiting for the
     * review-wide "Iniciar cribratge" action and then deciding them one
     * at a time. A still-'imported' row is promoted into the queue on
     * the fly before deciding it. Rows that can't take a new decision
     * right now (reviewers_required already reached by other reviewers,
     * and this reviewer hasn't decided it before) are skipped and
     * counted rather than erroring out the whole batch.
     */
    public function screenBulk(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $uid = (int) Auth::id();

        // Whatever filter/search/page the reviewer had open when they hit
        // "Fer el Cribatge" — sent back so the list doesn't reset to the
        // unfiltered default after the action completes.
        $back = (string) ($_POST['back'] ?? '/reviews/' . $rid . '/references');
        if (!str_starts_with($back, '/')) {
            $back = '/reviews/' . $rid . '/references';
        }

        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude', 'maybe'], true)) {
            Session::flash('error', __('references.screen_bulk_none'));
            redirect($back);
        }

        $reason = $decision === 'exclude' ? (trim((string) ($_POST['reason'] ?? '')) ?: null) : null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;

        $raw = (array) ($_POST['reference_ids'] ?? []);
        $ids = array_values(array_unique(array_map('intval', $raw)));
        $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));

        if ($ids === []) {
            Session::flash('error', __('references.screen_bulk_none'));
            redirect($back);
        }

        @set_time_limit(180);
        $stage = 'ta';
        $decided = 0;
        $skipped = 0;
        $becameConflict = false;
        foreach ($ids as $refId) {
            $ref = Reference::find($refId);
            if ($ref === null || (int) $ref['review_id'] !== $rid) {
                continue;
            }
            if ((string) $ref['status'] === 'imported') {
                Reference::setStatus($refId, 'ta_screening');
                $ref['status'] = 'ta_screening';
            }
            if (!ScreeningService::canDecide($ref, $uid, $review, $stage)) {
                $skipped++;
                continue;
            }
            if (ScreeningService::recordDecision($review, $refId, $uid, $stage, $decision, $reason, $notes)) {
                $becameConflict = true;
            }
            $decided++;
        }

        if ($becameConflict) {
            ScreeningService::notifyConflictResolvers($review, $rid, $uid, '/reviews/' . $rid . '/screen');
        }

        ActivityLog::record('references.screened_bulk', [
            'decision' => $decision,
            'decided'  => $decided,
            'skipped'  => $skipped,
        ], $rid);

        $decisionLabel = __('screening.' . $decision);
        if ($decided === 0) {
            Session::flash('error', __('references.screen_bulk_all_skipped', $skipped));
        } elseif ($skipped > 0) {
            Session::flash('success', __('references.screen_bulk_partial', $decided, $decisionLabel, $skipped));
        } else {
            Session::flash('success', __('references.screen_bulk_ok', $decided, $decisionLabel));
        }
        redirect($back);
    }

    /**
     * Run the deduplication pass on demand from the references toolbar.
     * Marks exact matches as duplicates and creates pending fuzzy
     * candidates; the user then lands on the Duplicates page to review
     * what was flagged and wipe the confirmed dupes in bulk.
     */
    public function findDuplicates(string $id): void
    {
        $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        @set_time_limit(180);
        try {
            $r = DeduplicationService::run($rid);
        } catch (\Throwable $e) {
            ActivityLog::record('references.dedup_failed', [
                'review_id' => $rid,
                'error'     => $e->getMessage(),
            ], $rid);
            Session::flash('error', __('references.dedup_failed'));
            redirect('/reviews/' . $rid . '/references');
        }

        ActivityLog::record('references.dedup_run_manual', [
            'exact' => $r['exact'],
            'fuzzy' => $r['fuzzy'],
        ], $rid);
        Session::flash('success', __('references.dedup_done', $r['exact'], $r['fuzzy']));
        redirect('/reviews/' . $rid . '/duplicates');
    }

    private function memberOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
