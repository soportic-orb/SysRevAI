<?php

declare(strict_types=1);

/**
 * Persistent secondary navigation that shows up under the topbar whenever
 * the user is browsing pages of a specific review. Rendered by
 * layouts/app.php from the request URI; controllers don't need to know.
 *
 * Uses the platform's existing .btn classes so it inherits the same look
 * as every other button across the app — no bespoke styling.
 *
 * @var array  $review     // review row (id, title, status, owner_id, screening_mode)
 * @var string $activeKey  // one of: overview screening fulltext extraction rob exports references team protocol
 */

use SysRevAI\Core\Auth;
use SysRevAI\Models\AiUsage;

$id      = (int) $review['id'];
$isOwner = (int) $review['owner_id'] === (int) Auth::id();

// Token spend is sensitive operational data: only the review owner and
// platform admins/owners see the badge and may open the breakdown page.
// Everyone else gets neither the pill nor the totals query.
$canSeeAiSpend = $isOwner || Auth::hasRole('owner', 'admin');

if ($canSeeAiSpend) {
    // AI-spend badge totals — best-effort: an exception here (table missing
    // in a partially-migrated install, transient DB blip) must not block the
    // page render. Cheap aggregate query covered by the existing index on
    // (review_id).
    try {
        $aiTotals = AiUsage::forReview($id);
    } catch (\Throwable) {
        $aiTotals = ['input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.0];
    }
    $aiTokens = (int) $aiTotals['input_tokens'] + (int) $aiTotals['output_tokens'];
    $aiUsdToEur = (float) (setting('currency.usd_to_eur') ?? 0.92);
    $aiEur = (float) $aiTotals['cost_usd'] * $aiUsdToEur;
    $aiEurLabel = $aiEur < 0.005
        ? number_format($aiEur, 4, ',', '.') . ' €'
        : number_format($aiEur, $aiEur < 1 ? 4 : 2, ',', '.') . ' €';
    $aiTokensLabel = $aiTokens >= 1000
        ? number_format($aiTokens / 1000, 1, ',', '.') . 'k'
        : (string) $aiTokens;
}

/** @var array<int,array{key:string,url:string,label:string,style:string,icon?:string,owner?:bool,kindOnly?:string}> $links */
$links = [
    ['key' => 'overview',   'url' => "/reviews/{$id}",              'label' => __('reviews.overview'),      'style' => 'ghost'],
    ['key' => 'screening',  'url' => "/reviews/{$id}/screen",       'label' => __('screening.title'),       'style' => 'primary'],
    ['key' => 'fulltext',   'url' => "/reviews/{$id}/full-text",    'label' => __('fulltext.title'),        'style' => 'primary'],
    ['key' => 'extraction', 'url' => "/reviews/{$id}/extraction",   'label' => __('extraction.title'),      'style' => 'primary'],
    // Risk-of-bias appraisal is not part of PRISMA-ScR (scoping
    // reviews map rather than synthesise), so the tab is hidden when
    // the review's kind is 'scoping'.
    ['key' => 'rob',        'url' => "/reviews/{$id}/risk-of-bias", 'label' => __('rob.title'),             'style' => 'primary', 'kindOnly' => 'systematic'],
    ['key' => 'exports',    'url' => "/reviews/{$id}/exports",      'label' => __('exports.title'),         'style' => 'ghost', 'icon' => 'export'],
    ['key' => 'references', 'url' => "/reviews/{$id}/references",   'label' => __('references.title'),      'style' => 'ghost', 'icon' => 'references'],
    ['key' => 'team',       'url' => "/reviews/{$id}/team",         'label' => __('team.title'),            'style' => 'ghost', 'icon' => 'team',     'owner' => true],
    ['key' => 'protocol',   'url' => "/reviews/{$id}/protocol",     'label' => __('reviews.edit_protocol'), 'style' => 'ghost', 'icon' => 'protocol', 'owner' => true],
];
?>
<nav class="review-subnav" aria-label="<?= e(__('reviews.subnav_aria')) ?>">
    <div class="review-subnav__inner">
        <div class="review-subnav__head">
            <a class="review-subnav__back" href="/reviews" title="<?= e(__('nav.reviews')) ?>" aria-label="<?= e(__('nav.reviews')) ?>">
                <span aria-hidden="true">&larr;</span>
            </a>
            <a class="review-subnav__name" href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a>
            <span class="tag tag--<?= e((string) $review['status']) ?>"><?= e(__('reviews.status_' . $review['status'])) ?></span>
            <?php
            // Kind tag — surfaces whether this is a systematic or scoping
            // review at every level of the deep nav. Soft style so it
            // reads as metadata, not as a state badge.
            $kindLabel = (string) ($review['kind'] ?? 'systematic');
            ?>
            <span class="tag tag--soft" title="<?= e(__('reviews.kind')) ?>">
                <?= e(__('reviews.kind_' . $kindLabel)) ?>
            </span>
            <?php if ($isOwner): ?>
                <?php $confirmMsg = $review['status'] === 'archived'
                    ? __('common.confirm_unarchive')
                    : __('common.confirm_archive'); ?>
                <form method="post" action="/reviews/<?= $id ?>/archive"
                      class="inline-form review-subnav__archive"
                      data-confirm="<?= e($confirmMsg) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--xs btn--ghost" type="submit">
                        <?php $iconName = 'archive'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                        <?= e($review['status'] === 'archived' ? __('reviews.unarchive') : __('reviews.archive')) ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php
            // Permanent delete is destructive and irreversible. Only the
            // review owner and platform admins/owners see it, and only
            // when the review has been archived first — archiving is the
            // explicit cooldown step that prevents accidental loss.
            $canDeleteReview = ($isOwner || Auth::hasRole('owner', 'admin'))
                && $review['status'] === 'archived';
            ?>
            <?php if ($canDeleteReview): ?>
                <form method="post" action="/reviews/<?= $id ?>/delete"
                      class="inline-form review-subnav__delete"
                      data-confirm="<?= e(__('common.confirm_delete_review')) ?>"
                      data-confirm-tone="danger"
                      data-confirm-button="<?= e(__('reviews.delete')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--xs btn--danger-solid" type="submit">
                        <?php $iconName = 'trash'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                        <?= e(__('reviews.delete')) ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canSeeAiSpend): ?>
            <!-- AI-spend badge: live token / EUR totals for this review,
                 linking to the per-call breakdown. Gray surface, lime
                 text matches the platform's primary accent. Token spend
                 is sensitive operational data, so the badge is restricted
                 to the review owner and platform admins. -->
            <a class="review-subnav__ai-badge"
               href="/reviews/<?= $id ?>/ai-usage"
               title="<?= e(__('ai_usage.badge_tooltip', $aiTokensLabel, $aiEurLabel)) ?>">
                <?php $iconName = 'euro'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                <span class="review-subnav__ai-tokens"><?= e($aiTokensLabel) ?></span>
                <span class="review-subnav__ai-cost">(<?= e($aiEurLabel) ?>)</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="review-subnav__actions">
            <?php foreach ($links as $link): ?>
                <?php if (!empty($link['owner']) && !$isOwner) continue; ?>
                <?php if (!empty($link['kindOnly']) && (string) ($review['kind'] ?? 'systematic') !== $link['kindOnly']) continue; ?>
                <?php
                    $isActive = $link['key'] === $activeKey;
                    $classes  = 'btn btn--xs ';
                    $classes .= $isActive ? 'btn--primary is-active' : ('btn--' . $link['style']);
                ?>
                <a class="<?= e($classes) ?>" href="<?= e($link['url']) ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <?php if (!empty($link['icon'])): ?>
                        <?php $iconName = $link['icon']; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                    <?php endif; ?>
                    <?= e($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
