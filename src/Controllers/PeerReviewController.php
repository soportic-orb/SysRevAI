<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\ReferencePeerReview;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;

/**
 * AI peer-review rubric per reference. Renders the cached rubric on
 * GET; POST re-runs Claude with the extracted full-text and persists
 * the new evaluation, overwriting the previous one.
 */
final class PeerReviewController
{
    public function show(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $row = ReferencePeerReview::find((int) $reference['id']);
        $rubric = $row !== null ? ReferencePeerReview::decode($row) : null;

        $ft = ReferenceFullText::find((int) $reference['id']);
        $hasText = $ft !== null && trim((string) ($ft['extracted_text'] ?? '')) !== '';

        echo View::render('peer_review/show', [
            'review'    => $review,
            'reference' => $reference,
            'rubric'    => $rubric,
            'rubricRow' => $row,
            'hasText'   => $hasText,
            'axes'      => ReferencePeerReview::AXES,
        ]);
    }

    public function generate(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $ridInt = (int) $id;
        $refIdInt = (int) $reference['id'];
        $back = '/reviews/' . $ridInt . '/references/' . $refIdInt . '/peer-review';

        $ft = ReferenceFullText::find($refIdInt);
        $text = (string) ($ft['extracted_text'] ?? '');
        if (trim($text) === '') {
            Session::flash('error', __('peer_review.no_text'));
            redirect($back);
        }

        @set_time_limit(180);
        $authors = json_decode((string) ($reference['authors_json'] ?? ''), true) ?: [];
        $result = ClaudeService::fromSettings()->peerReviewRubric([
            'title'   => $reference['title']   ?? '',
            'year'    => $reference['year']    ?? null,
            'journal' => $reference['journal'] ?? '',
            'authors' => $authors,
            'doi'     => $reference['doi']     ?? '',
        ], $text, current_locale(), $ridInt);

        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            $err = (string) ($result['error'] ?? 'failed');
            ActivityLog::record('peer_review.failed', ['reference_id' => $refIdInt, 'error' => $err], $ridInt);
            Session::flash('error', __('peer_review.failed'));
            redirect($back);
        }

        $data = $this->sanitise($result['data']);
        ReferencePeerReview::save(
            $refIdInt,
            $data,
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7'),
            (int) Auth::id()
        );
        ActivityLog::record('peer_review.generated', ['reference_id' => $refIdInt], $ridInt);
        Session::flash('success', __('peer_review.generated'));
        redirect($back);
    }

    /**
     * Whitelist + clamp the model's JSON response so the view never has
     * to defend against an out-of-range score or a missing key.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sanitise(array $data): array
    {
        $clamp = static function ($v): int {
            return max(0, min(100, (int) (is_numeric($v) ? $v : 0)));
        };
        $clean = [];
        foreach (ReferencePeerReview::AXES as $axis) {
            $clean[$axis] = $clamp($data[$axis] ?? 0);
            $clean[$axis . '_note'] = trim((string) ($data[$axis . '_note'] ?? ''));
        }
        $clean['summary']         = trim((string) ($data['summary']         ?? ''));
        $clean['devils_advocate'] = trim((string) ($data['devils_advocate'] ?? ''));
        $clean['overall']         = trim((string) ($data['overall']         ?? ''));
        return $clean;
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function loadOrDeny(int $id, int $refId): array
    {
        $review = Review::find($id);
        if ($review === null || !Review::userCanAccess($id, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        $reference = Reference::find($refId);
        if ($reference === null || (int) $reference['review_id'] !== $id) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            exit;
        }
        return [$review, $reference];
    }
}
