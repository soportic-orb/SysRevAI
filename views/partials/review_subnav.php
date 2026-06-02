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

/** @var array<int,array{key:string,url:string,label:string,style:string,icon?:string,owner?:bool}> $links */
$links = [
    ['key' => 'overview',   'url' => "/reviews/{$id}",              'label' => __('reviews.overview'),      'style' => 'ghost'],
    ['key' => 'screening',  'url' => "/reviews/{$id}/screen",       'label' => __('screening.title'),       'style' => 'primary'],
    ['key' => 'fulltext',   'url' => "/reviews/{$id}/full-text",    'label' => __('fulltext.title'),        'style' => 'primary'],
    ['key' => 'extraction', 'url' => "/reviews/{$id}/extraction",   'label' => __('extraction.title'),      'style' => 'primary'],
    ['key' => 'rob',        'url' => "/reviews/{$id}/risk-of-bias", 'label' => __('rob.title'),             'style' => 'primary'],
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
            <?php if ($isOwner): ?>
                <?php $confirmMsg = $review['status'] === 'archived'
                    ? __('common.confirm_unarchive')
                    : __('common.confirm_archive'); ?>
                <form method="post" action="/reviews/<?= $id ?>/archive"
                      class="inline-form review-subnav__archive"
                      onsubmit="return confirm('<?= e($confirmMsg) ?>');">
                    <?= csrf_field() ?>
                    <button class="btn btn--xs btn--ghost" type="submit">
                        <?php $iconName = 'archive'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                        <?= e($review['status'] === 'archived' ? __('reviews.unarchive') : __('reviews.archive')) ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- AI-spend badge: live token / EUR totals for this review,
                 linking to the per-call breakdown. Gray surface, lime
                 text matches the platform's primary accent. -->
            <a class="review-subnav__ai-badge"
               href="/reviews/<?= $id ?>/ai-usage"
               title="<?= e(__('ai_usage.badge_tooltip', $aiTokensLabel, $aiEurLabel)) ?>">
                <?php $iconName = 'usage'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                <span class="review-subnav__ai-tokens"><?= e($aiTokensLabel) ?></span>
                <span class="review-subnav__ai-cost">(<?= e($aiEurLabel) ?>)</span>
            </a>
        </div>
        <div class="review-subnav__actions">
            <?php foreach ($links as $link): ?>
                <?php if (!empty($link['owner']) && !$isOwner) continue; ?>
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
