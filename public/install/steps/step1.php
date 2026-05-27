<?php

declare(strict_types=1);

/** Step 1 — System requirements check. Rendered inside index.php. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$report     = check_requirements();
$groups     = $report['groups'];
$blockingOk = $report['blocking_ok'];

$badge = static function (string $status): string {
    return match ($status) {
        'ok'    => '<span class="badge badge--ok" title="' . h(t('common.status_ok')) . '">&#10003;</span>',
        'warn'  => '<span class="badge badge--warn" title="' . h(t('common.status_warn')) . '">!</span>',
        default => '<span class="badge badge--fail" title="' . h(t('common.status_fail')) . '">&#10007;</span>',
    };
};
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step1.title')) ?></h1>
    <p class="lead"><?= h(t('step1.intro')) ?></p>

    <?php if ($blockingOk): ?>
        <div class="alert alert--success"><?= h(t('step1.all_good')) ?></div>
    <?php else: ?>
        <div class="alert alert--error"><?= h(t('step1.fix_needed')) ?></div>
    <?php endif; ?>

    <?php foreach ($groups as $groupKey => $items): ?>
        <h2 class="group-title"><?= h(t('step1.' . $groupKey)) ?></h2>
        <ul class="req-list">
            <?php foreach ($items as $item): ?>
                <li class="req-item req-item--<?= h($item['status']) ?>">
                    <?= $badge($item['status']) ?>
                    <div class="req-item__body">
                        <span class="req-item__label"><?= h($item['label']) ?></span>
                        <span class="req-item__detail"><?= h($item['detail']) ?></span>
                        <?php if (!empty($item['fix'])): ?>
                            <span class="req-item__fix"><?= h($item['fix']) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="1">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>

        <div class="actions__right">
            <form method="post" action="index.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="next">
                <input type="hidden" name="step" value="1">
                <button type="submit" class="btn btn--ghost"><?= h(t('nav.recheck')) ?></button>
            </form>
            <form method="post" action="index.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="next">
                <input type="hidden" name="step" value="1">
                <button type="submit" class="btn btn--primary" <?= $blockingOk ? '' : 'disabled' ?>>
                    <?= h(t('nav.next')) ?> &rarr;
                </button>
            </form>
        </div>
    </div>
</section>
