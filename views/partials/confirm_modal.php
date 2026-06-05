<?php

declare(strict_types=1);

/**
 * Centred confirmation modal. Included once per layout; the global JS
 * (in app.js) populates and shows it whenever a <form> tagged with
 * data-confirm="<message>" is submitted. Native confirm() is no
 * longer used anywhere in the platform — every destructive action
 * routes through this dialog so the experience is consistent.
 *
 * Per-form overrides via data-* attributes:
 *   data-confirm-title="…"     Title shown at the top of the modal.
 *   data-confirm-button="…"    Label of the "yes" button.
 *   data-confirm-tone="danger"  Use the red solid style for the "yes"
 *                              button (default: primary).
 */
?>
<dialog class="info-modal info-modal--confirm" id="appConfirmModal" aria-labelledby="appConfirmTitle">
    <div class="info-modal__inner">
        <button type="button" class="info-modal__close"
                data-info-close
                aria-label="<?= e(__('common.close')) ?>">&times;</button>
        <h3 id="appConfirmTitle"><?= e(__('common.confirm_title')) ?></h3>
        <p id="appConfirmBody" class="confirm-modal__body"></p>
        <div class="actions">
            <button type="button" class="btn btn--ghost" data-info-close>
                <?= e(__('common.cancel')) ?>
            </button>
            <button type="button" class="btn btn--primary" id="appConfirmYes"
                    data-default-label="<?= e(__('common.confirm_yes')) ?>">
                <?= e(__('common.confirm_yes')) ?>
            </button>
        </div>
    </div>
</dialog>
