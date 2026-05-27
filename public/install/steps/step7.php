<?php

declare(strict_types=1);

/** Step 7 — Finalization. Writes .env, creates the admin and seals the installer. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

require_once base_path('config/donate.php');

$result = $_SESSION['install']['finalize_result'] ?? null;
$admin  = $_SESSION['install']['admin'] ?? [];
?>

<section class="card">
    <?php if ($result !== null && !empty($result['success'])): ?>

        <div class="success-hero">
            <span class="success-hero__icon">&#10003;</span>
            <h1 class="card__title"><?= h(t('step7.title')) ?></h1>
            <p class="lead"><?= h(t('step7.success')) ?></p>
        </div>

        <h2 class="group-title"><?= h(t('step7.summary_title')) ?></h2>
        <ul class="req-list">
            <li class="req-item req-item--ok"><span class="badge badge--ok">&#10003;</span>
                <div class="req-item__body"><span class="req-item__label"><?= h(t('step7.tables_created')) ?></span></div></li>
            <li class="req-item req-item--ok"><span class="badge badge--ok">&#10003;</span>
                <div class="req-item__body"><span class="req-item__label"><?= h(t('step7.env_written')) ?></span></div></li>
            <li class="req-item req-item--ok"><span class="badge badge--ok">&#10003;</span>
                <div class="req-item__body"><span class="req-item__label"><?= h(t('step7.admin_created', $admin['email'] ?? '')) ?></span></div></li>
            <li class="req-item req-item--ok"><span class="badge badge--ok">&#10003;</span>
                <div class="req-item__body"><span class="req-item__label"><?= h(t('step7.lock_created')) ?></span></div></li>
        </ul>

        <h2 class="group-title"><?= h(t('step7.recommend_title')) ?></h2>
        <ul class="recommend">
            <li><?= h(t('step7.rec_cron')) ?></li>
            <li><?= h(t('step7.rec_backup')) ?></li>
            <li><?= h(t('step7.rec_perms')) ?></li>
            <li><?= h(t('step7.rec_delete')) ?></li>
        </ul>

        <div class="donate-card">
            <p class="donate-card__text"><?= h(t('step7.donate')) ?></p>
            <a class="btn btn--donate" href="<?= h(DONATE_URL) ?>" target="_blank" rel="noopener noreferrer">
                &#10084; <?= h(t('step7.donate_btn')) ?>
            </a>
        </div>

        <div class="actions">
            <a class="btn btn--ghost" href="../../docs" target="_blank" rel="noopener"><?= h(t('step7.docs')) ?></a>
            <a class="btn btn--primary" href="../"><?= h(t('step7.go_dashboard')) ?> &rarr;</a>
        </div>

    <?php elseif ($result !== null): ?>

        <h1 class="card__title"><?= h(t('step7.title')) ?></h1>
        <div class="alert alert--error">
            <ul style="margin:0;padding-left:18px">
                <?php if (!$result['env']['ok']): ?>
                    <li><?= h(t('step7.env_fail', $result['env']['error'] ?? '')) ?></li>
                <?php endif; ?>
                <?php if (!$result['admin']['ok']): ?>
                    <li><?= h(t('step7.admin_fail', $result['admin']['error'] ?? '')) ?></li>
                <?php endif; ?>
                <?php if (!$result['lock']['ok']): ?>
                    <li><?= h(t('step7.lock_fail', $result['lock']['error'] ?? '')) ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="actions">
            <form method="post" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="back">
                <input type="hidden" name="step" value="7">
                <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
            </form>
            <form method="post" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="finalize">
                <input type="hidden" name="step" value="7">
                <button type="submit" class="btn btn--primary"><?= h(t('step7.finalize')) ?></button>
            </form>
        </div>

    <?php else: ?>

        <h1 class="card__title"><?= h(t('step7.title')) ?></h1>
        <p class="lead"><?= h(t('step7.summary_title')) ?></p>
        <ul class="req-list">
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step7.tables_created')) ?></span></div></li>
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step7.env_written')) ?></span></div></li>
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step7.admin_created', $admin['email'] ?? '')) ?></span></div></li>
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step7.lock_created')) ?></span></div></li>
        </ul>

        <div class="actions">
            <form method="post" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="back">
                <input type="hidden" name="step" value="7">
                <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
            </form>
            <form method="post" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="finalize">
                <input type="hidden" name="step" value="7">
                <button type="submit" class="btn btn--primary"><?= h(t('step7.finalize')) ?></button>
            </form>
        </div>

    <?php endif; ?>
</section>
