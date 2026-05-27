<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Duplicate;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;

/**
 * Manual resolution of fuzzy duplicate candidates (level 3 semantic AI lands in
 * Phase 7). Exact (level 1) matches are already auto-confirmed at import.
 */
final class DuplicatesController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        echo View::render('duplicates/index', [
            'review'     => $review,
            'duplicates' => Duplicate::pendingForReview((int) $id),
        ]);
    }

    public function resolve(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $dupId = (int) ($_POST['duplicate_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');

        $dup = Duplicate::find($dupId);
        if ($dup === null || (int) $dup['review_id'] !== $rid) {
            redirect('/reviews/' . $rid . '/duplicates');
        }

        if ($decision === 'confirm') {
            // Mark the second record as the duplicate, keep the first.
            Reference::setStatus((int) $dup['ref_b_id'], 'duplicate');
            Duplicate::setStatus($dupId, 'confirmed');
            ActivityLog::record('duplicate.confirmed', ['duplicate_id' => $dupId], $rid);
        } elseif ($decision === 'reject') {
            Duplicate::setStatus($dupId, 'rejected');
            ActivityLog::record('duplicate.rejected', ['duplicate_id' => $dupId], $rid);
        }

        Session::flash('success', __('duplicates.resolved'));
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
