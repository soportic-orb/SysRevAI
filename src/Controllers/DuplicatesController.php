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
        $uid = (int) Auth::id();
        $canDelete = (int) $review['owner_id'] === $uid || Auth::hasRole('owner', 'admin');
        echo View::render('duplicates/index', [
            'review'     => $review,
            'duplicates' => Duplicate::pendingForReview((int) $id),
            // References auto-flagged by the dedup pass — listed here so
            // the user can wipe the confirmed exact dupes with a single
            // click instead of opening each one.
            'confirmedDupes' => Reference::confirmedDuplicates((int) $id),
            'canDelete'      => $canDelete,
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

    /** Level-3 semantic duplicate check with Claude (advisory). */
    public function checkAi(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $dupId = (int) ($_POST['duplicate_id'] ?? 0);
        $dup = Duplicate::find($dupId);
        if ($dup === null || (int) $dup['review_id'] !== $rid) {
            redirect('/reviews/' . $rid . '/duplicates');
        }

        $refA = Reference::find((int) $dup['ref_a_id']);
        $refB = Reference::find((int) $dup['ref_b_id']);
        $result = \SysRevAI\Services\ClaudeService::fromSettings()
            ->checkSemanticDuplicate($refA ?? [], $refB ?? [], $rid);

        if (($result['ok'] ?? false) && is_array($result['data'] ?? null)) {
            $d = $result['data'];
            $conf = (float) ($d['confidence'] ?? 0);
            $reason = (string) ($d['reason'] ?? '');
            Duplicate::updateAi($dupId, $conf, $reason);
            $verdict = !empty($d['duplicate']) ? __('duplicates.ai_yes') : __('duplicates.ai_no');
            Session::flash('success', __('duplicates.ai_result', $verdict, (string) round($conf * 100), $reason));
        } else {
            Session::flash('error', __('duplicates.ai_failed'));
        }
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
