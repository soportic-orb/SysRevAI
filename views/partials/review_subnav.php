<?php

declare(strict_types=1);

/**
 * Persistent secondary navigation that shows up under the topbar whenever
 * the user is browsing pages of a specific review. Rendered by
 * layouts/app.php from the request URI; controllers don't need to know.
 *
 * @var array  $review     // review row (id, title, status, owner_id, screening_mode)
 * @var string $activeKey  // one of: overview screening fulltext extraction rob exports references import team protocol
 */

use SysRevAI\Core\Auth;

$id      = (int) $review['id'];
$isOwner = (int) $review['owner_id'] === (int) Auth::id();

/** @var array<int,array{key:string,url:string,label:string,style:string,owner?:bool}> $links */
$links = [
    ['key' => 'overview',   'url' => "/reviews/{$id}",                'label' => __('reviews.overview'),     'style' => 'ghost'],
    ['key' => 'screening',  'url' => "/reviews/{$id}/screen",         'label' => __('screening.title'),       'style' => 'primary'],
    ['key' => 'fulltext',   'url' => "/reviews/{$id}/full-text",      'label' => __('fulltext.title'),        'style' => 'primary'],
    ['key' => 'extraction', 'url' => "/reviews/{$id}/extraction",     'label' => __('extraction.title'),      'style' => 'primary'],
    ['key' => 'rob',        'url' => "/reviews/{$id}/risk-of-bias",   'label' => __('rob.title'),             'style' => 'primary'],
    ['key' => 'exports',    'url' => "/reviews/{$id}/exports",        'label' => __('exports.title'),         'style' => 'ghost'],
    ['key' => 'references', 'url' => "/reviews/{$id}/references",     'label' => __('references.title'),      'style' => 'ghost'],
    ['key' => 'import',     'url' => "/reviews/{$id}/import",         'label' => __('import.title'),          'style' => 'ghost'],
    ['key' => 'team',       'url' => "/reviews/{$id}/team",           'label' => __('team.title'),            'style' => 'ghost', 'owner' => true],
    ['key' => 'protocol',   'url' => "/reviews/{$id}/protocol",       'label' => __('reviews.edit_protocol'), 'style' => 'ghost', 'owner' => true],
];
?>
<nav class="review-subnav" aria-label="<?= e(__('reviews.subnav_aria')) ?>">
    <div class="review-subnav__inner">
        <div class="review-subnav__title">
            <a class="review-subnav__back" href="/reviews" title="<?= e(__('nav.reviews')) ?>">&larr;</a>
            <a href="/reviews/<?= $id ?>" class="review-subnav__name"><?= e((string) $review['title']) ?></a>
            <span class="tag tag--<?= e((string) $review['status']) ?>"><?= e(__('reviews.status_' . $review['status'])) ?></span>
        </div>
        <div class="review-subnav__links">
            <?php foreach ($links as $link): ?>
                <?php if (!empty($link['owner']) && !$isOwner) continue; ?>
                <a class="review-subnav__btn review-subnav__btn--<?= e($link['style']) ?>
                       <?= $link['key'] === $activeKey ? 'is-active' : '' ?>"
                   href="<?= e($link['url']) ?>">
                    <?= e($link['label']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($isOwner): ?>
                <form method="post" action="/reviews/<?= $id ?>/archive" class="review-subnav__form">
                    <?= csrf_field() ?>
                    <button class="review-subnav__btn review-subnav__btn--ghost" type="submit">
                        <?= e($review['status'] === 'archived' ? __('reviews.unarchive') : __('reviews.archive')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
