<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\ExclusionReason;
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
        $rid = (int) $id;

        // Full-text screening can't begin while any reference still hasn't
        // reached a final T/A outcome — otherwise references could enter FT
        // screening piecemeal while T/A is still in progress on others.
        if ($this->stage === 'ft' && !ScreeningService::taScreeningComplete($rid)) {
            Session::flash('error', __('fulltext.ta_not_finished'));
            redirect($this->basePath($rid));
        }

        $moved = ScreeningService::startScreening($rid, $this->stage);
        ActivityLog::record('screening.started', ['stage' => $this->stage, 'moved' => $moved], $rid);
        Session::flash('success', __('screening.started', $moved));
        redirect($this->basePath($rid));
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

        // Reopening a specific reference — from the "referències
        // revisades" history, or the prev/next nav arrows — is only
        // honoured when it actually belongs to this review AND has
        // entered this stage's pipeline (still pending or already
        // decided/finalized). This can't be used to jump to an
        // 'imported'/'duplicate' row that hasn't reached this stage, or
        // to any reference in a different review.
        $uid = (int) Auth::id();
        $requestedId = (int) ($_GET['reference_id'] ?? 0);
        $overrideReferenceId = null;
        $ownDecision = null;
        if ($requestedId > 0) {
            $candidate = Reference::find($requestedId);
            if ($candidate !== null && (int) $candidate['review_id'] === $rid
                && in_array((string) $candidate['status'], ScreeningService::stageStatuses($this->stage), true)) {
                $overrideReferenceId = $requestedId;
                $ownDecision = ScreeningDecision::reviewerDecision($requestedId, $uid, $this->stage);
            }
        }

        $state = $this->buildScreenState($review, $rid, $overrideReferenceId);
        $data = array_merge([
            'review'      => $review,
            'reasons'     => ExclusionReason::forReview($rid),
            'stage'       => $this->stage,
            'ownDecision' => $ownDecision,
        ], $state, $this->extraScreenData($review, $state['reference']));

        echo View::render($this->screenView(), $data);
    }

    /** Own past decisions for this review/stage, newest first. */
    public function history(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $uid = (int) Auth::id();

        echo View::render('screening/history', [
            'review'    => $review,
            'rows'      => ScreeningDecision::historyForReviewer($rid, $uid, $this->stage),
            'basePath'  => $this->basePath($rid),
            'stageName' => $this->stage === 'ft' ? __('fulltext.title') : __('screening.title'),
        ]);
    }

    /**
     * The "what should this reviewer see right now" snapshot: shared by the
     * full page render and the AJAX response after a decision, so the
     * screening page can swap to the next reference in place (via fetch)
     * instead of a full navigation — which is what was silently dropping
     * the browser's Fullscreen API state on every decision.
     *
     * @param ?int $overrideReferenceId When set (and the reference belongs
     *        to this review), shows that specific reference instead of the
     *        next pending one — used to reopen an already-decided
     *        reference from the history list.
     */
    private function buildScreenState(array $review, int $rid, ?int $overrideReferenceId = null): array
    {
        $uid = (int) Auth::id();
        $reference = null;
        if ($overrideReferenceId !== null) {
            $candidate = Reference::find($overrideReferenceId);
            if ($candidate !== null && (int) $candidate['review_id'] === $rid) {
                $reference = $candidate;
            }
        }
        if ($reference === null) {
            $reference = ScreeningService::nextReference($rid, $uid, $review, $this->stage);
        }
        $canCoordinate = $this->canCoordinate($review);

        // Adjacent references for the prev/next nav arrows. "Previous" steps
        // back through anything already screened; "next" skips ahead to the
        // next one still pending — see ScreeningService for why they're not
        // symmetric. Fetched here (not just a boolean) so the full-text view
        // — which has no AJAX nav and just links to ?reference_id=X — has an
        // actual id to point at, not only whether one exists.
        $prevRef = $reference !== null
            ? ScreeningService::previousReference($rid, (int) $reference['id'], $this->stage)
            : null;
        $nextRef = $reference !== null
            ? ScreeningService::nextReferenceAfter($rid, (int) $reference['id'], $uid, $review, $this->stage)
            : null;

        return [
            'reference'       => $reference,
            'pico'            => $reference ? Review::pico($review) : [],
            'pending'         => ScreeningService::pendingForReviewer($rid, $uid, $review, $this->stage),
            'completed'       => ScreeningDecision::reviewerCompleted($rid, $uid, $this->stage),
            'totalReferences' => ScreeningService::totalReferences($rid),
            'totalInStage'    => ScreeningService::totalInStage($rid, $this->stage),
            'conflicts'       => $canCoordinate ? ScreeningService::conflictCount($rid, $review, $this->stage) : 0,
            'canCoordinate'   => $canCoordinate,
            'hasPrev'         => $prevRef !== null,
            'hasNext'         => $nextRef !== null,
            'prevReferenceId' => $prevRef !== null ? (int) $prevRef['id'] : null,
            'nextReferenceId' => $nextRef !== null ? (int) $nextRef['id'] : null,
        ];
    }

    /** AJAX prev/next nav — reopens the adjacent reference without a page reload. */
    public function nav(string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $uid = (int) Auth::id();

        $currentId = (int) ($_GET['reference_id'] ?? 0);
        $direction = (string) ($_GET['direction'] ?? '');

        $target = null;
        if ($currentId > 0 && $direction === 'prev') {
            $target = ScreeningService::previousReference($rid, $currentId, $this->stage);
        } elseif ($currentId > 0 && $direction === 'next') {
            $target = ScreeningService::nextReferenceAfter($rid, $currentId, $uid, $review, $this->stage);
        }

        if ($target === null) {
            echo json_encode(['ok' => false]);
            return;
        }

        $targetId = (int) $target['id'];
        $ownDecision = ScreeningDecision::reviewerDecision($targetId, $uid, $this->stage);
        echo json_encode(['ok' => true, 'ownDecision' => $ownDecision] + $this->buildScreenState($review, $rid, $targetId), JSON_UNESCAPED_UNICODE);
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '';
    }

    /** Ends the request: a JSON error for AJAX callers, a flash + redirect otherwise. */
    private function failOrRedirect(bool $ajax, int $rid, ?string $errorKey): void
    {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $errorKey ?? 'invalid']);
            exit;
        }
        if ($errorKey !== null) {
            Session::flash('error', __('screening.' . $errorKey));
        }
        redirect($this->basePath($rid));
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
        $ajax = $this->isAjax();

        if ($this->coordinatorActive($rid)) {
            $this->failOrRedirect($ajax, $rid, 'coord_no_screen');
        }

        $referenceId = (int) ($_POST['reference_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['include', 'exclude', 'maybe'], true)) {
            $this->failOrRedirect($ajax, $rid, null);
        }
        $reference = Reference::find($referenceId);
        if ($reference === null || (int) $reference['review_id'] !== $rid) {
            $this->failOrRedirect($ajax, $rid, null);
        }
        if (!ScreeningService::canDecide($reference, (int) Auth::id(), $review, $this->stage)) {
            // Reference already reached its reviewers_required quota (e.g. two
            // other reviewers decided while this one had the page open).
            $this->failOrRedirect($ajax, $rid, 'quota_reached');
        }

        $reason = $decision === 'exclude' ? trim((string) ($_POST['reason'] ?? '')) : null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $time = max(0, min(3600, (int) ($_POST['time_spent'] ?? 0)));

        // Optional traceability: if the reviewer asked for an AI suggestion
        // before deciding, the client posts the raw JSON back so we can
        // persist exactly what the model recommended at decision time.
        $aiJson = null;
        $rawAi = (string) ($_POST['ai_suggestion_json'] ?? '');
        if ($rawAi !== '') {
            $decoded = json_decode($rawAi, true);
            if (is_array($decoded) && isset($decoded['recommendation'])) {
                $clean = [
                    'recommendation' => (string) $decoded['recommendation'],
                    'confidence'     => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
                    'reason'         => isset($decoded['reason']) ? (string) $decoded['reason'] : '',
                    'language'       => isset($decoded['language']) ? (string) $decoded['language'] : '',
                    'shown_at'       => isset($decoded['shown_at']) ? (string) $decoded['shown_at'] : '',
                ];
                $aiJson = json_encode($clean, JSON_UNESCAPED_UNICODE);
            }
        }

        $becameConflict = ScreeningService::recordDecision(
            $review,
            $referenceId,
            (int) Auth::id(),
            $this->stage,
            $decision,
            $reason,
            $notes,
            $time,
            $aiJson
        );
        if ($becameConflict) {
            $this->notifyResolvers($review, $rid, (int) Auth::id());
        }

        if ($ajax) {
            // Hand back the next reference in place instead of redirecting,
            // so the page never unloads — that's what kept exiting the
            // browser's Fullscreen API mode after every decision.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true] + $this->buildScreenState($review, $rid), JSON_UNESCAPED_UNICODE);
            return;
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
        // The AI's "reason" comes back in the reviewer's interface language
        // so they can read it without an extra translation step.
        $locale = \SysRevAI\Core\I18n::locale();
        $result = \SysRevAI\Services\ClaudeService::fromSettings()
            ->suggestScreeningDecision($reference, $protocol, $rid, $locale);

        // Surface the language back to the client so the saved trace
        // records exactly which locale was used at decision time.
        if (is_array($result) && ($result['ok'] ?? false)) {
            $result['language'] = $locale;
        }
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
        ScreeningService::notifyConflictResolvers($review, $reviewId, $exceptUserId, $this->basePath($reviewId));
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
