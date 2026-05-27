<?php

declare(strict_types=1);

/** Step 2 — Composer dependencies. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$deps   = dependencies_status();
$result = $_SESSION['install']['deps_result'] ?? null;
$ok     = $deps['present'] && $deps['classes_ok'];
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step2.title')) ?></h1>
    <p class="lead"><?= h(t('step2.intro')) ?></p>

    <?php if ($ok): ?>
        <div class="alert alert--success"><?= h(t('step2.present')) ?> <?= h(t('step2.classes_ok')) ?></div>
    <?php elseif ($deps['present']): ?>
        <div class="alert alert--error"><?= h(t('step2.classes_missing')) ?></div>
        <ul class="req-list">
            <?php foreach ($deps['missing'] as $pkg): ?>
                <li class="req-item req-item--fail">
                    <span class="badge badge--fail">&#10007;</span>
                    <div class="req-item__body"><span class="req-item__label"><?= h($pkg) ?></span></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="alert alert--error"><?= h(t('step2.missing')) ?></div>
        <p class="lead">
            <?= $deps['composer_bin']
                ? '&#10003; ' . h(t('step2.composer_found')) . ' (' . h($deps['composer_bin']) . ')'
                : '&#9888; ' . h(t('step2.composer_missing')) ?>
        </p>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deps_install">
            <input type="hidden" name="step" value="2">
            <button type="submit" class="btn btn--primary"><?= h(t('step2.try_install')) ?></button>
        </form>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['ok'] ? 'alert--success' : 'alert--error' ?>">
            <?= h($result['message']) ?>
        </div>
        <?php if (!empty($result['output'])): ?>
            <pre class="log-output"><?= h($result['output']) ?></pre>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$ok): ?>
        <h2 class="group-title"><?= h(t('step2.manual_title')) ?></h2>
        <ul class="req-list">
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step2.manual_a')) ?></span></div></li>
            <li class="req-item"><div class="req-item__body"><span class="req-item__detail"><?= h(t('step2.manual_b')) ?></span></div></li>
        </ul>
        <div class="note"><span class="note__icon">!</span><p><?= h(t('step2.skip_warn')) ?></p></div>
    <?php endif; ?>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="2">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="next">
            <input type="hidden" name="step" value="2">
            <button type="submit" class="btn <?= $ok ? 'btn--primary' : 'btn--ghost' ?>">
                <?= $ok ? h(t('nav.next')) : h(t('step2.continue_anyway')) ?> &rarr;
            </button>
        </form>
    </div>
</section>
