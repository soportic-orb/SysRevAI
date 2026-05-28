<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $duplicates */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('duplicates.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('duplicates.intro')) ?></p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <?php if ($duplicates === []): ?>
        <div class="empty-state"><p><?= e(__('duplicates.none')) ?></p></div>
    <?php else: ?>
        <?php foreach ($duplicates as $d): ?>
            <div class="section-card dup-pair">
                <div class="dup-side">
                    <span class="muted"><?= e(__('duplicates.keep')) ?></span>
                    <strong><?= e((string) ($d['a_title'] ?: '—')) ?></strong>
                    <span class="muted"><?= e((string) $d['a_journal']) ?> <?= !empty($d['a_year']) ? '· ' . (int) $d['a_year'] : '' ?></span>
                </div>
                <div class="dup-side">
                    <span class="muted"><?= e(__('duplicates.candidate')) ?></span>
                    <strong><?= e((string) ($d['b_title'] ?: '—')) ?></strong>
                    <span class="muted"><?= e((string) $d['b_journal']) ?> <?= !empty($d['b_year']) ? '· ' . (int) $d['b_year'] : '' ?></span>
                </div>
                <div class="dup-meta">
                    <span class="tag tag--soft"><?= e((string) $d['method']) ?> · <?= e((string) round((float) $d['confidence'] * 100)) ?>%</span>
                    <div class="btn-row">
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/resolve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="decision" value="confirm">
                            <button class="btn btn--danger btn--sm"><?= e(__('duplicates.confirm')) ?></button>
                        </form>
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/resolve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button class="btn btn--ghost btn--sm"><?= e(__('duplicates.reject')) ?></button>
                        </form>
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/check-ai" data-ai-action>
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <button class="btn btn--ghost btn--sm">&#10024; <?= e(__('duplicates.ai_check')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
