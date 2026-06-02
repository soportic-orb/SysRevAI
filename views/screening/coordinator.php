<?php

declare(strict_types=1);

/** @var array $review */
/** @var array $rows */
/** @var string $basePath */
$id = (int) $review['id'];
$basePath = $basePath ?? '/reviews/' . $id . '/screen';
?>
<div class="page">
    <div class="alert alert--warn coord-banner" data-no-toast>
        &#9888; <?= e(__('screening.coord_banner')) ?>
        <form method="post" action="<?= e($basePath) ?>/coordinator" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm"><?= e(__('screening.coord_exit')) ?></button>
        </form>
    </div>

    <div class="page__head">
        <h1 class="page__title"><?= e(__('screening.coordinator_view')) ?></h1>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('screening.all_done')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(__('references.col_study')) ?></th><th><?= e(__('screening.decisions')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= e((string) ($r['title'] ?: '—')) ?></strong></td>
                                <td>
                                    <?php if (($r['decisions'] ?? []) === []): ?>
                                        <span class="muted"><?= e(__('screening.no_decisions')) ?></span>
                                    <?php else: ?>
                                        <?php foreach ($r['decisions'] as $d): ?>
                                            <span class="tag tag--<?= e((string) $d['decision']) ?>"><?= e((string) $d['reviewer_name']) ?>: <?= e(__('screening.' . $d['decision'])) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
