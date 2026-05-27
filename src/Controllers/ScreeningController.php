<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\ExclusionReason;
use SysRevAI\Models\Notification;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewUser;
use SysRevAI\Models\ScreeningDecision;
use SysRevAI\Services\ScreeningService;

/**
 * Title/abstract screening: one reference at a time with reviewer blinding,
 * a conflict queue, and a coordinator (un-blinded) overview.
 */
final class ScreeningController
{
    private const STAGE = 'ta';

    public function start(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $moved = ScreeningService::startScreening((int) $id);
        ActivityLog::record('screening.started', ['moved' => $moved], (int) $id);
        Session::flash('success', __('screening.started', $moved));
        redirect('/reviews/' . (int) $id . '/screen');
    }

    public function screen(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        if ($this->coordinatorActive($rid)) {
            $this->renderCoordinator($review);
            return;
        }

        $uid = (int) Auth::id();
        $reference = ScreeningService::nextReference($rid, $uid, $review, self::STAGE);
        $pending = ScreeningService::pendingForReviewer($rid, $uid, $review, self::STAGE);
        $canCoordinate = $this->canCoordinate($review);

        echo View::render('screening/screen', [
            'review'        => $review,
            'reference'     => $reference,
            'pico'          => $reference ? Review::pico($review) : [],
            'reasons'       => ExclusionReason::forReview($rid),
            'pending'       => $pending,
            'completed'     => ScreeningDecision::reviewerCompleted($rid, $uid, self::STAGE),
            'conflicts'     => $canCoordinate ? ScreeningService::conflictCount($rid, $review, self::STAGE) : 0,
            'canCoordinate' => $canCoordinate,
        ]);
    }

    public function decide(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        if ($this->coordinatorActive($rid)) {
            Session::flash('error', __('screening.coord_no_screen'));
            redirect('/reviews/' . $rid . '/screen');
        }

        $referenceId = (int) ($_POST['reference_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude', 'maybe'], true)) {
            redirect('/reviews/' . $rid . '/screen');
        }
        $reference = Reference::find($referenceId);
        if ($reference === null || (int) $reference['review_id'] !== $rid) {
            redirect('/reviews/' . $rid . '/screen');
        }

        $reason = $decision === 'exclude' ? trim((string) ($_POST['reason'] ?? '')) : null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $time = max(0, min(3600, (int) ($_POST['time_spent'] ?? 0)));

        $required = ScreeningService::requiredReviewers($review);
        $before = ScreeningDecision::decidedCount($referenceId, self::STAGE);

        ScreeningDecision::record($referenceId, (int) Auth::id(), self::STAGE, $decision, $reason, $notes, $time);
        ScreeningService::evaluate($referenceId, $review, self::STAGE);

        // If this decision completed the required set without finalizing, it is
        // now a conflict — notify the resolvers once.
        $after = ScreeningDecision::decidedCount($referenceId, self::STAGE);
        $fresh = Reference::find($referenceId);
        if ($before < $required && $after >= $required
            && $fresh !== null && $fresh['status'] === 'ta_screening') {
            $this->notifyResolvers($review, $rid, (int) Auth::id());
        }

        redirect('/reviews/' . $rid . '/screen');
    }

    public function conflicts(string $id): void
    {
        $review = $this->resolverOrDeny((int) $id);
        echo View::render('screening/conflicts', [
            'review'    => $review,
            'conflicts' => ScreeningService::conflicts((int) $id, $review, self::STAGE),
        ]);
    }

    public function resolveConflict(string $id): void
    {
        $review = $this->resolverOrDeny((int) $id);
        $rid = (int) $id;
        $referenceId = (int) ($_POST['reference_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude'], true)) {
            redirect('/reviews/' . $rid . '/conflicts');
        }
        $reference = Reference::find($referenceId);
        if ($reference !== null && (int) $reference['review_id'] === $rid) {
            ScreeningService::resolveConflict($referenceId, (int) Auth::id(), self::STAGE, $decision);
            ActivityLog::record('screening.conflict_resolved', ['reference_id' => $referenceId, 'decision' => $decision], $rid);
            Session::flash('success', __('screening.conflict_resolved'));
        }
        redirect('/reviews/' . $rid . '/conflicts');
    }

    public function toggleCoordinator(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        if (!$this->canCoordinate($review)) {
            redirect('/reviews/' . $rid . '/screen');
        }
        $active = !$this->coordinatorActive($rid);
        $_SESSION['coordinator'][$rid] = $active;
        ActivityLog::record($active ? 'screening.coordinator_on' : 'screening.coordinator_off', [], $rid);
        redirect('/reviews/' . $rid . '/screen');
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function renderCoordinator(array $review): void
    {
        $rid = (int) $review['id'];
        // All references that have entered screening, with every reviewer's row.
        $refs = Reference::forReview($rid, 'ta_screening', '', 1, 100);
        $rows = [];
        foreach ($refs['rows'] as $r) {
            $r['decisions'] = ScreeningDecision::forReference((int) $r['id'], self::STAGE);
            $rows[] = $r;
        }
        echo View::render('screening/coordinator', [
            'review' => $review,
            'rows'   => $rows,
        ]);
    }

    private function notifyResolvers(array $review, int $reviewId, int $exceptUserId): void
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
                '/reviews/' . $reviewId . '/conflicts',
                $reviewId
            );
        }
    }

    private function coordinatorActive(int $reviewId): bool
    {
        return !empty($_SESSION['coordinator'][$reviewId]);
    }

    private function canCoordinate(array $review): bool
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

    private function resolverOrDeny(int $reviewId): array
    {
        $review = $this->memberOrDeny($reviewId);
        $resolvers = ReviewUser::resolverIds($reviewId, (int) $review['owner_id']);
        if (!in_array((int) Auth::id(), $resolvers, true)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
