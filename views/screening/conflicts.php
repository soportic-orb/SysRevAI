<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $conflicts */
/** @var string $basePath */
$id = (int) $review['id'];
$basePath = $basePath ?? '/reviews/' . $id . '/screen';
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="<?= e($basePath) ?>"><?= e(__('screening.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('screening.conflicts_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('screening.conflicts_intro')) ?></p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <?php if ($conflicts === []): ?>
        <div class="empty-state"><p><?= e(__('screening.no_conflicts')) ?></p></div>
    <?php else: ?>
        <?php foreach ($conflicts as $c): $authors = json_decode((string) $c['authors_json'], true) ?: []; ?>
            <div class="section-card">
                <h2 class="section__subtitle"><?= e((string) ($c['title'] ?: '—')) ?></h2>
                <p class="muted"><?= e(implode('; ', array_slice($authors, 0, 4))) ?><?php if (!empty($c['year'])): ?> · <?= (int) $c['year'] ?><?php endif; ?></p>

                <ul class="decision-rows">
                    <?php foreach ($c['decisions'] as $d): ?>
                        <li>
                            <strong><?= e((string) $d['reviewer_name']) ?></strong>
                            <span class="tag tag--<?= e((string) $d['decision']) ?>"><?= e(__('screening.' . $d['decision'])) ?></span>
                            <?php if (!empty($d['reason'])): ?><span class="muted"><?= e((string) $d['reason']) ?></span><?php endif; ?>
                            <?php if (!empty($d['notes'])): ?><span class="muted">— <?= e((string) $d['notes']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="btn-row">
                    <form method="post" action="<?= e($basePath) ?>/conflicts/resolve">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reference_id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="decision" value="include">
                        <button class="btn btn--include"><?= e(__('screening.resolve_include')) ?></button>
                    </form>
                    <form method="post" action="<?= e($basePath) ?>/conflicts/resolve">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reference_id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="decision" value="exclude">
                        <button class="btn btn--exclude"><?= e(__('screening.resolve_exclude')) ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
