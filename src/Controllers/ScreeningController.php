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
 *
 * Not final: FullTextScreeningController extends this to reuse the entire
 * pipeline under the `ft` stage.
 */
class ScreeningController
{
    protected string $stage = 'ta';

    public function start(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $moved = ScreeningService::startScreening((int) $id, $this->stage);
        ActivityLog::record('screening.started', ['stage' => $this->stage, 'moved' => $moved], (int) $id);
        Session::flash('success', __('screening.started', $moved));
        redirect($this->basePath((int) $id));
    }

    /** URL prefix for screen redirects, depending on stage. */
    protected function basePath(int $reviewId): string
    {
        return $this->stage === 'ft'
            ? '/reviews/' . $reviewId . '/full-text'
            : '/reviews/' . $reviewId . '/screen';
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
        $reference = ScreeningService::nextReference($rid, $uid, $review, $this->stage);
        $pending = ScreeningService::pendingForReviewer($rid, $uid, $review, $this->stage);
        $canCoordinate = $this->canCoordinate($review);

        $data = array_merge([
            'review'        => $review,
            'reference'     => $reference,
            'pico'          => $reference ? Review::pico($review) : [],
            'reasons'       => ExclusionReason::forReview($rid),
            'pending'       => $pending,
            'completed'     => ScreeningDecision::reviewerCompleted($rid, $uid, $this->stage),
            'conflicts'     => $canCoordinate ? ScreeningService::conflictCount($rid, $review, $this->stage) : 0,
            'canCoordinate' => $canCoordinate,
            'stage'         => $this->stage,
        ], $this->extraScreenData($review, $reference));

        echo View::render($this->screenView(), $data);
    }

    /** Subclasses override to add stage-specific data (e.g. PDF + chat for FT). */
    protected function extraScreenData(array $review, ?array $reference): array
    {
        return [];
    }

    /** Subclasses override to render a different view per stage. */
    protected function screenView(): string
    {
        return 'screening/screen';
    }

    public function decide(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        if ($this->coordinatorActive($rid)) {
            Session::flash('error', __('screening.coord_no_screen'));
            redirect($this->basePath($rid));
        }

        $referenceId = (int) ($_POST['reference_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude', 'maybe'], true)) {
            redirect($this->basePath($rid));
        }
        $reference = Reference::find($referenceId);
        if ($reference === null || (int) $reference['review_id'] !== $rid) {
            redirect($this->basePath($rid));
        }

        $reason = $decision === 'exclude' ? trim((string) ($_POST['reason'] ?? '')) : null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $time = max(0, min(3600, (int) ($_POST['time_spent'] ?? 0)));

        $required = ScreeningService::requiredReviewers($review);
        $before = ScreeningDecision::decidedCount($referenceId, $this->stage);

        ScreeningDecision::record($referenceId, (int) Auth::id(), $this->stage, $decision, $reason, $notes, $time);
        ScreeningService::evaluate($referenceId, $review, $this->stage);

        // If this decision completed the required set without finalizing, it is
        // now a conflict — notify the resolvers once.
        $after = ScreeningDecision::decidedCount($referenceId, $this->stage);
        $fresh = Reference::find($referenceId);
        if ($before < $required && $after >= $required
            && $fresh !== null && $fresh['status'] === 'ta_screening') {
            $this->notifyResolvers($review, $rid, (int) Auth::id());
        }

        redirect($this->basePath($rid));
    }

    /** AI screening suggestion (advisory). Returns JSON. */
    public function suggest(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        header('Content-Type: application/json; charset=utf-8');

        $reference = Reference::find((int) ($_GET['reference_id'] ?? 0));
        if ($reference === null || (int) $reference['review_id'] !== $rid) {
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            return;
        }

        $protocol = [
            'question'  => $review['question'],
            'pico'      => Review::pico($review),
            'inclusion' => $review['inclusion_criteria'],
            'exclusion' => $review['exclusion_criteria'],
        ];
        $result = \SysRevAI\Services\ClaudeService::fromSettings()
            ->suggestScreeningDecision($reference, $protocol, $rid);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function conflicts(string $id): void
    {
        $review = $this->resolverOrDeny((int) $id);
        echo View::render('screening/conflicts', [
            'review'    => $review,
            'conflicts' => ScreeningService::conflicts((int) $id, $review, $this->stage),
            'basePath'  => $this->basePath((int) $id),
        ]);
    }

    public function resolveConflict(string $id): void
    {
        $review = $this->resolverOrDeny((int) $id);
        $rid = (int) $id;
        $referenceId = (int) ($_POST['reference_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude'], true)) {
            redirect($this->basePath($rid) . '/conflicts');
        }
        $reference = Reference::find($referenceId);
        if ($reference !== null && (int) $reference['review_id'] === $rid) {
            ScreeningService::resolveConflict($referenceId, (int) Auth::id(), $this->stage, $decision);
            ActivityLog::record('screening.conflict_resolved', ['reference_id' => $referenceId, 'decision' => $decision], $rid);
            Session::flash('success', __('screening.conflict_resolved'));
        }
        redirect($this->basePath($rid) . '/conflicts');
    }

    public function toggleCoordinator(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        if (!$this->canCoordinate($review)) {
            redirect($this->basePath($rid));
        }
        $active = !$this->coordinatorActive($rid);
        $_SESSION['coordinator'][$rid] = $active;
        ActivityLog::record($active ? 'screening.coordinator_on' : 'screening.coordinator_off', [], $rid);
        redirect($this->basePath($rid));
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function renderCoordinator(array $review): void
    {
        $rid = (int) $review['id'];
        $status = $this->stage === 'ft' ? 'ft_screening' : 'ta_screening';
        $refs = Reference::forReview($rid, $status, '', 1, 100);
        $rows = [];
        foreach ($refs['rows'] as $r) {
            $r['decisions'] = ScreeningDecision::forReference((int) $r['id'], $this->stage);
            $rows[] = $r;
        }
        echo View::render('screening/coordinator', [
            'review'   => $review,
            'rows'     => $rows,
            'basePath' => $this->basePath($rid),
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
                $this->basePath($reviewId) . '/conflicts',
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
