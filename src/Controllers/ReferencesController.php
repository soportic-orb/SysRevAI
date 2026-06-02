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
use SysRevAI\Services\FileStorage;

final class ReferencesController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $status = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Reference::forReview($rid, $status, $search, $page);

        echo View::render('references/index', [
            'review'        => $review,
            'rows'          => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page,
            'perPage'       => 25,
            'status'        => $status,
            'search'        => $search,
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

        Reference::delete($referenceId);

        ActivityLog::record('references.deleted', [
            'reference_id' => $referenceId,
            'title'        => (string) ($ref['title'] ?? ''),
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
