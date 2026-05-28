<?php

declare(strict_types=1);

/** @var array $review */
/** @var array{total:int,attempted:int,with_text:int} $coverage */
/** @var array $bySource */
$id = (int) $review['id'];
$total = (int) $coverage['total'];
$with  = (int) $coverage['with_text'];
$attempted = (int) $coverage['attempted'];
$pct = $total > 0 ? (int) round($with / $total * 100) : 0;
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('fulltext.coverage_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('fulltext.coverage_intro')) ?></p>
    </div>

    <div class="section-card">
        <div class="metrics">
            <div class="metric"><span class="metric__value"><?= $total ?></span><span class="metric__label"><?= e(__('fulltext.cov_total')) ?></span></div>
            <div class="metric"><span class="metric__value"><?= $attempted ?></span><span class="metric__label"><?= e(__('fulltext.cov_attempted')) ?></span></div>
            <div class="metric"><span class="metric__value"><?= $with ?></span><span class="metric__label"><?= e(__('fulltext.cov_with_text')) ?></span></div>
            <div class="metric"><span class="metric__value"><?= $pct ?>%</span><span class="metric__label"><?= e(__('fulltext.cov_pct')) ?></span></div>
        </div>
        <div class="progress" style="margin-top:14px"><div class="progress__bar" style="width: <?= $pct ?>%"></div></div>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('fulltext.cov_top_sources')) ?></h2>
        <?php if ($bySource === []): ?>
            <p class="muted"><?= e(__('fulltext.cov_no_data')) ?></p>
        <?php else: ?>
            <ul class="reason-list">
                <?php foreach ($bySource as $row): ?>
                    <li><strong><?= e((string) $row['source']) ?></strong> · <?= (int) $row['hits'] ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('fulltext.fallback_title')) ?></h2>
        <p class="muted"><?= e(__('fulltext.fallback_intro')) ?></p>
        <ul class="reason-list">
            <li><a href="https://doi.org/" target="_blank" rel="noopener noreferrer"><?= e(__('fulltext.fallback_doi')) ?></a></li>
            <li><a href="https://scholar.google.com/" target="_blank" rel="noopener noreferrer"><?= e(__('fulltext.fallback_scholar')) ?></a></li>
            <li><?= e(__('fulltext.fallback_upload')) ?></li>
        </ul>
    </div>
</div>
