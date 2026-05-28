<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\RetrievalQueue;
use SysRevAI\Models\Review;

/**
 * Read-only listing and lightweight controls for the retrieval queue,
 * scoped to a review. Members see the queue; only the owner can cancel.
 */
final class RetrievalQueueController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        echo View::render('fulltext/queue', [
            'review'   => $review,
            'jobs'     => RetrievalQueue::forReview((int) $id, 100),
            'summary'  => RetrievalQueue::summary(),
            'pollUrl'  => '/reviews/' . (int) $id . '/full-text-queue/poll',
        ]);
    }

    /** JSON snapshot for the page's AJAX refresh. */
    public function poll(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        header('Content-Type: application/json; charset=utf-8');
        $jobs = RetrievalQueue::forReview((int) $id, 100);
        echo json_encode([
            'ok'      => true,
            'summary' => RetrievalQueue::summary(),
            'jobs'    => array_map(static fn ($j) => [
                'id'             => (int) $j['id'],
                'reference_id'   => (int) $j['reference_id'],
                'ref_title'      => (string) $j['ref_title'],
                'status'         => (string) $j['status'],
                'priority'       => (int) $j['priority'],
                'error_message'  => $j['error_message'] ?? null,
                'created_at'     => (string) $j['created_at'],
                'completed_at'   => $j['completed_at'] ?? null,
                'requested_by_name' => $j['requested_by_name'] ?? null,
            ], $jobs),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function cancelAll(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $count = RetrievalQueue::cancelPendingForReview((int) $id);
        ActivityLog::record('fulltext.queue_cancel_all', ['count' => $count], (int) $id);
        Session::flash('success', __('fulltext.cancelled', $count));
        redirect('/reviews/' . (int) $id . '/full-text-queue');
    }

    /* ── Guards ────────────────────────────────────────────────────────── */

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

    private function ownerOrDeny(int $reviewId): array
    {
        $review = $this->memberOrDeny($reviewId);
        if ((int) $review['owner_id'] !== (int) Auth::id()) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
