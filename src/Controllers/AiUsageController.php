<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\AiUsage;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;

/**
 * Detailed AI-spend breakdown for a single review. Lists every Claude
 * call (feature, model, in/out tokens, cost) so the team can see exactly
 * where the token budget went. Reachable from the badge in the review
 * sub-nav.
 */
final class AiUsageController
{
    public function index(string $id): void
    {
        $review = $this->ownerOrAdminOrDeny((int) $id);
        $rid = (int) $id;

        $totals = AiUsage::forReview($rid);
        $rows   = AiUsage::listForReview($rid, 200);

        // Per-row recomputed cost — the stored cost reflects the rate that
        // was in PRICES when the call was made. The "cost" column in the
        // view is what we already paid; we just expose the EUR projection
        // alongside it for the reader.
        $usdToEur = (float) (setting('currency.usd_to_eur') ?? 0.92);

        echo View::render('reviews/ai_usage', [
            'review'        => $review,
            'rows'          => $rows,
            'totals'        => $totals,
            'usdToEur'      => $usdToEur,
            'priceTable'    => ClaudeService::pricingTable(),
            'activeKey'     => 'ai-usage',
        ]);
    }

    /**
     * Token spend is sensitive operational data: visible only to the
     * review owner and platform admins/owners. Other reviewers — even
     * legitimate members of the review — get a 403.
     */
    private function ownerOrAdminOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        $uid    = (int) Auth::id();
        $isReviewOwner = $review !== null && (int) $review['owner_id'] === $uid;
        $isPlatformAdmin = Auth::hasRole('owner', 'admin');
        if ($review === null || (!$isReviewOwner && !$isPlatformAdmin)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
