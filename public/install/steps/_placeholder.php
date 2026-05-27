<?php

declare(strict_types=1);

/**
 * Shared placeholder body for wizard steps not yet implemented (2–7).
 * Relies on $step being in scope (set by index.php).
 */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$isLast = ($step >= INSTALLER_TOTAL_STEPS - 1);
?>

<section class="card">
    <h1 class="card__title"><?= h(t('steps.' . $step)) ?></h1>

    <div class="placeholder">
        <span class="placeholder__icon">&#9881;</span>
        <p><?= h(t('common.coming_soon')) ?></p>
    </div>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="<?= (int) $step ?>">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>

        <?php if (!$isLast): ?>
            <form method="post" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="next">
                <input type="hidden" name="step" value="<?= (int) $step ?>">
                <button type="submit" class="btn btn--primary"><?= h(t('nav.next')) ?> &rarr;</button>
            </form>
        <?php else: ?>
            <button type="button" class="btn btn--primary" disabled><?= h(t('nav.finish')) ?></button>
        <?php endif; ?>
    </div>
</section>
