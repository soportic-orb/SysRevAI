<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullTextStatus;
use SysRevAI\Models\RetrievalQueue;
use SysRevAI\Models\Review;
use SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService;

/**
 * Reviewer-facing actions over the full-text retrieval module: single-shot
 * synchronous retrieval, bulk enqueue, retry-failed, and the coverage view.
 */
final class FullTextRetrievalController
{
    /** Trigger the cascade synchronously for one reference. */
    public function retrieve(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);

        $exhaustive = (bool) (setting('fulltext.exhaustive') ?? false);
        $result = (new FullTextRetrievalService())->retrieveFor((int) $reference['id'], $exhaustive);

        ActivityLog::record('fulltext.retrieve_one', [
            'reference_id' => (int) $reference['id'],
            'success'      => (bool) $result['success'],
            'attempts'     => count($result['attempts']),
        ], (int) $id);

        Session::flash(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? __('fulltext.retrieved_via', $result['result']->source ?? '?')
                : __('fulltext.not_found_anywhere')
        );
        redirect('/reviews/' . (int) $id . '/references');
    }

    /** Enqueue every reference in the review that has no successful retrieval yet. */
    public function enqueueAll(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $refs = Database::table('references');
        $rfs  = Database::table('reference_fulltext_status');
        $queue = Database::table('retrieval_queue');
        $rows = Database::select(
            "SELECT r.id FROM `{$refs}` r
             LEFT JOIN `{$rfs}` s ON s.reference_id = r.id
             WHERE r.review_id = ?
               AND r.status <> 'duplicate'
               AND (s.reference_id IS NULL OR s.has_fulltext = 0)
               AND r.id NOT IN (
                   SELECT reference_id FROM `{$queue}`
                   WHERE status IN ('pending','processing')
               )",
            [$rid]
        );

        $count = 0;
        foreach ($rows as $row) {
            RetrievalQueue::enqueue((int) $row['id'], (int) Auth::id());
            $count++;
        }
        ActivityLog::record('fulltext.enqueue_all', ['count' => $count], $rid);
        Session::flash($count > 0 ? 'success' : 'error', __('fulltext.enqueued', $count));
        redirect('/reviews/' . $rid . '/full-text-queue');
    }

    /** Enqueue references whose last retrieval attempt did not yield content. */
    public function retryFailed(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $refs = Database::table('references');
        $rfs  = Database::table('reference_fulltext_status');
        $rows = Database::select(
            "SELECT s.reference_id FROM `{$rfs}` s
             JOIN `{$refs}` r ON r.id = s.reference_id
             WHERE r.review_id = ? AND s.has_fulltext = 0 AND s.attempts_count > 0",
            [$rid]
        );

        $count = 0;
        foreach ($rows as $row) {
            RetrievalQueue::enqueue((int) $row['reference_id'], (int) Auth::id(), 3);
            $count++;
        }
        ActivityLog::record('fulltext.retry_failed', ['count' => $count], $rid);
        Session::flash('success', __('fulltext.enqueued', $count));
        redirect('/reviews/' . $rid . '/full-text-queue');
    }

    public function coverage(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $cov = ReferenceFullTextStatus::coverage($rid);

        $attempts = Database::table('retrieval_attempts');
        $refs     = Database::table('references');
        $bySource = Database::select(
            "SELECT a.source, COUNT(*) AS hits FROM `{$attempts}` a
             JOIN `{$refs}` r ON r.id = a.reference_id
             WHERE r.review_id = ? AND a.status = 'success'
             GROUP BY a.source ORDER BY hits DESC",
            [$rid]
        );

        echo View::render('fulltext/coverage', [
            'review'   => $review,
            'coverage' => $cov,
            'bySource' => $bySource,
        ]);
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

    /** @return array{0:array,1:array} [review, reference] */
    private function loadOrDeny(int $reviewId, int $refId): array
    {
        $review = $this->memberOrDeny($reviewId);
        $reference = Reference::find($refId);
        if ($reference === null || (int) $reference['review_id'] !== $reviewId) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return [$review, $reference];
    }
}
