<?php

declare(strict_types=1);

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $pico */
/** @var array $metrics */
/** @var array $members */
/** @var array $reasons */
$id = (int) $review['id'];
$isOwner = (int) $review['owner_id'] === (int) Auth::id();
$metricKeys = ['imported', 'duplicate', 'ta_screening', 'ta_included', 'ft_screening', 'ft_included', 'ft_excluded', 'extracted'];
?>
<div class="page">
    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews"><?= e(__('nav.reviews')) ?></a> /</div>
            <h1 class="page__title"><?= e((string) $review['title']) ?></h1>
            <span class="tag tag--<?= e((string) $review['status']) ?>"><?= e(__('reviews.status_' . $review['status'])) ?></span>
            <span class="tag tag--soft"><?= e(__('reviews.mode_' . $review['screening_mode'])) ?></span>
        </div>
        <?php if ($isOwner): ?>
            <div class="btn-row">
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/protocol"><?= e(__('reviews.edit_protocol')) ?></a>
                <form method="post" action="/reviews/<?= $id ?>/archive">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e($review['status'] === 'archived' ? __('reviews.unarchive') : __('reviews.archive')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="metrics">
        <?php foreach ($metricKeys as $key): ?>
            <div class="metric">
                <span class="metric__value"><?= (int) ($metrics[$key] ?? 0) ?></span>
                <span class="metric__label"><?= e(__('reviews.metric_' . $key)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid-2">
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('reviews.protocol')) ?></h2>
            <?php if (!empty($review['question'])): ?>
                <p><strong><?= e(__('reviews.question')) ?>:</strong><br><?= nl2br(e((string) $review['question'])) ?></p>
            <?php endif; ?>
            <dl class="kv">
                <?php foreach (['population', 'intervention', 'comparison', 'outcome', 'study_design'] as $f): ?>
                    <?php if ($pico[$f] !== ''): ?>
                        <dt><?= e(__('reviews.pico_' . $f)) ?></dt><dd><?= e((string) $pico[$f]) ?></dd>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
            <?php if (!empty($review['inclusion_criteria'])): ?>
                <p><strong><?= e(__('reviews.inclusion')) ?>:</strong><br><?= nl2br(e((string) $review['inclusion_criteria'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($review['exclusion_criteria'])): ?>
                <p><strong><?= e(__('reviews.exclusion')) ?>:</strong><br><?= nl2br(e((string) $review['exclusion_criteria'])) ?></p>
            <?php endif; ?>
        </div>

        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('reviews.team')) ?></h2>
            <ul class="member-list">
                <?php foreach ($members as $m): ?>
                    <li>
                        <span><?= e((string) $m['name']) ?> <span class="muted"><?= e((string) $m['email']) ?></span></span>
                        <span class="tag tag--soft"><?= e((string) $m['role']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="field-help"><?= e(__('reviews.team_note')) ?></p>

            <h2 class="section__subtitle" style="margin-top:18px"><?= e(__('reviews.exclusion_reasons')) ?></h2>
            <?php if ($reasons === []): ?>
                <p class="muted"><?= e(__('reviews.no_reasons')) ?></p>
            <?php else: ?>
                <ul class="reason-list">
                    <?php foreach ($reasons as $r): ?>
                        <li><?= e((string) $r['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
