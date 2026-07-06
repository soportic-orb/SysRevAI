<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Duplicate;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Models\ScreeningDecision;
use SysRevAI\Services\DeduplicationService;
use SysRevAI\Services\FileStorage;

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

        $result = Reference::forReview($rid, $status, $search, $page, $perPage, $abstract, $source);

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
            $ids = Reference::idsForReview($rid, $status, $search, $abstract, $source);
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
     * Move selected references straight into the T/A screening queue —
     * lets a reviewer search or page through the list, pick specific
     * rows, and prioritize them without waiting for (or running) the
     * review-wide "Iniciar cribratge" action. Only rows still at
     * 'imported' are eligible; anything already past that (duplicate,
     * already screening, included/excluded, etc.) is silently skipped
     * and counted.
     */
    public function sendToScreeningBulk(string $id): void
    {
        $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $raw = (array) ($_POST['reference_ids'] ?? []);
        $ids = array_values(array_unique(array_map('intval', $raw)));
        $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));

        if ($ids === []) {
            Session::flash('error', __('references.send_screening_none'));
            redirect('/reviews/' . $rid . '/references');
        }

        $moved = 0;
        $skipped = 0;
        foreach ($ids as $refId) {
            $ref = Reference::find($refId);
            if ($ref === null || (int) $ref['review_id'] !== $rid) {
                continue;
            }
            if ((string) $ref['status'] !== 'imported') {
                $skipped++;
                continue;
            }
            Reference::setStatus($refId, 'ta_screening');
            $moved++;
        }

        ActivityLog::record('references.sent_to_screening', [
            'moved'   => $moved,
            'skipped' => $skipped,
        ], $rid);

        if ($moved === 0) {
            Session::flash('error', __('references.send_screening_all_skipped', $skipped));
        } elseif ($skipped > 0) {
            Session::flash('success', __('references.send_screening_partial', $moved, $skipped));
        } else {
            Session::flash('success', __('references.send_screening_ok', $moved));
        }
        redirect('/reviews/' . $rid . '/references');
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
