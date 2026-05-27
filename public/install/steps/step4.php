<?php

declare(strict_types=1);

/** Step 4 — Run migrations + seed data. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$result   = $_SESSION['install']['migrate_result'] ?? null;
$migrated = !empty($_SESSION['install']['migrated']);
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step4.title')) ?></h1>
    <p class="lead"><?= h(t('step4.intro')) ?></p>

    <?php if ($result !== null): ?>
        <?php if ($result['ok']): ?>
            <div class="alert alert--success"><?= h(t('step4.all_done')) ?> <?= h(t('step4.seed_ok')) ?></div>
        <?php else: ?>
            <div class="alert alert--error"><?= h(t('step4.failed', $result['error'] ?? '', '')) ?></div>
        <?php endif; ?>

        <ul class="req-list">
            <?php foreach ($result['log'] as $row): ?>
                <li class="req-item req-item--<?= $row['ok'] ? 'ok' : 'fail' ?>">
                    <span class="badge badge--<?= $row['ok'] ? 'ok' : 'fail' ?>"><?= $row['ok'] ? '&#10003;' : '&#10007;' ?></span>
                    <div class="req-item__body">
                        <span class="req-item__label"><?= h(t('step4.table_ok', $row['table'])) ?></span>
                        <?php if (!$row['ok']): ?>
                            <span class="req-item__fix"><?= h($row['error']) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!$migrated): ?>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_migrations">
            <input type="hidden" name="step" value="4">
            <button type="submit" class="btn btn--primary"><?= h(t('step4.btn_run')) ?></button>
        </form>
    <?php endif; ?>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="4">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="next">
            <input type="hidden" name="step" value="4">
            <button type="submit" class="btn btn--primary" <?= $migrated ? '' : 'disabled title="' . h(t('step4.run_first')) . '"' ?>>
                <?= h(t('nav.next')) ?> &rarr;
            </button>
        </form>
    </div>
</section>
