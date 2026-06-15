<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Models\Review;

/** @var array $review */
/** @var array $counts */
/** @var string $svg */
$id = (int) $review['id'];
$isScoping = Review::isScoping($review);
$registry  = $isScoping ? 'OSF' : 'PROSPERO';
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('exports.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('exports.intro')) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('admin_error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('exports.prisma_title')) ?></h2>
        <p class="muted"><?= e(__('exports.prisma_help')) ?></p>
        <div class="prisma-preview"><?= $svg ?></div>
        <div class="btn-row">
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/exports/prisma">&#11015; <?= e(__('exports.download_prisma')) ?></a>
        </div>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('exports.data_title')) ?></h2>
        <p class="muted"><?= e(__('exports.data_help')) ?></p>
        <div class="btn-row">
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/exports/csv">&#11015; CSV</a>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/exports/excel">&#11015; Excel (.xlsx)</a>
        </div>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('exports.doc_title')) ?></h2>
        <p class="muted"><?= e(__('exports.doc_help')) ?></p>
        <div class="btn-row">
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/exports/word">&#11015; Word (.docx)</a>
            <a class="btn btn--ghost" href="/reviews/<?= $id ?>/exports/revman">&#11015; RevMan 5 (.rm5)</a>
        </div>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle">
            <?= e(sprintf(__('exports.registration_title'), $registry)) ?>
        </h2>
        <p class="muted"><?= e(sprintf(__('exports.registration_help'), $registry)) ?></p>
        <div class="btn-row">
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/exports/registration">
                &#10024; <?= e(sprintf(__('exports.registration_open'), $registry)) ?>
            </a>
        </div>
    </div>
</div>
