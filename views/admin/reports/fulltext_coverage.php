<?php

declare(strict_types=1);

/** @var array{total:int,attempted:int,with_text:int} $global */
/** @var array $bySource */
/** @var array $perReview */
/** @var array $noTextRefs */
$total = (int) ($global['total'] ?? 0);
$attempted = (int) ($global['attempted'] ?? 0);
$with = (int) ($global['with_text'] ?? 0);
$pct = $total > 0 ? (int) round($with / $total * 100) : 0;
?>
<h1 class="section__title"><?= e(__('admin.reports.fulltext_title')) ?></h1>
<p class="section__intro"><?= e(__('admin.reports.fulltext_intro')) ?></p>

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
    <h2 class="section__subtitle"><?= e(__('admin.reports.top_sources')) ?></h2>
    <?php if ($bySource === []): ?>
        <p class="muted"><?= e(__('fulltext.cov_no_data')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(__('admin.fulltext.sources')) ?></th><th><?= e(__('admin.reports.hits')) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($bySource as $row): ?>
                        <tr><td><strong><?= e((string) $row['source']) ?></strong></td><td><?= (int) $row['hits'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.reports.per_review')) ?></h2>
    <?php if ($perReview === []): ?>
        <p class="muted"><?= e(__('fulltext.cov_no_data')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('nav.reviews')) ?></th>
                    <th><?= e(__('fulltext.cov_total')) ?></th>
                    <th><?= e(__('fulltext.cov_attempted')) ?></th>
                    <th><?= e(__('fulltext.cov_with_text')) ?></th>
                    <th><?= e(__('fulltext.cov_pct')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($perReview as $row):
                        $t = (int) ($row['total'] ?? 0);
                        $w = (int) ($row['with_text'] ?? 0);
                        $p = $t > 0 ? (int) round($w / $t * 100) : 0;
                    ?>
                        <tr>
                            <td><a href="/reviews/<?= (int) $row['review_id'] ?>"><?= e((string) ($row['review_title'] ?? '—')) ?></a></td>
                            <td><?= $t ?></td>
                            <td><?= (int) ($row['attempted'] ?? 0) ?></td>
                            <td><?= $w ?></td>
                            <td><?= $p ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="section-card">
    <div class="page__head--row">
        <h2 class="section__subtitle"><?= e(__('admin.reports.no_text_list')) ?></h2>
        <a class="btn btn--ghost btn--sm" href="/admin/reports/fulltext-coverage.csv">&#11015; CSV</a>
    </div>
    <?php if ($noTextRefs === []): ?>
        <p class="muted"><?= e(__('admin.reports.all_covered')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('references.col_study')) ?></th>
                    <th><?= e(__('nav.reviews')) ?></th>
                    <th><?= e(__('admin.reports.attempts')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($noTextRefs as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e(mb_strimwidth((string) ($row['ref_title'] ?? '—'), 0, 70, '…')) ?></strong>
                                <?php if (!empty($row['doi'])): ?><br><span class="muted">DOI: <?= e((string) $row['doi']) ?></span><?php endif; ?>
                            </td>
                            <td><a href="/reviews/<?= (int) $row['review_id'] ?>"><?= e(mb_strimwidth((string) ($row['review_title'] ?? '—'), 0, 50, '…')) ?></a></td>
                            <td><?= (int) ($row['attempts_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
