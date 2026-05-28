<?php

declare(strict_types=1);

/**
 * Reusable info modal. Renders a small round "i" button and a hidden
 * <dialog> that holds rich, didactic content about a feature. Designed
 * to be dropped next to a page heading on any pages that benefit from
 * a "what is this and how is it used?" explainer.
 *
 * Required vars (set before requiring this partial):
 *   $infoModalId    — DOM id (must be unique on the page)
 *   $infoTitle      — modal title shown in the dialog header
 *   $infoBody       — HTML body of the modal (already escaped by caller)
 * Optional:
 *   $infoButtonAria — accessible label for the trigger button
 */

$infoModalId    = (string) ($infoModalId ?? 'infoModal');
$infoTitle      = (string) ($infoTitle ?? '');
$infoBody       = (string) ($infoBody ?? '');
$infoButtonAria = (string) ($infoButtonAria ?? __('common.info_about', $infoTitle));
?>
<button type="button"
        class="info-btn"
        data-info-target="<?= e($infoModalId) ?>"
        aria-label="<?= e($infoButtonAria) ?>"
        title="<?= e($infoButtonAria) ?>">i</button>

<dialog class="info-modal" id="<?= e($infoModalId) ?>" aria-labelledby="<?= e($infoModalId) ?>-title">
    <div class="info-modal__inner">
        <header class="info-modal__head">
            <h2 class="info-modal__title" id="<?= e($infoModalId) ?>-title"><?= e($infoTitle) ?></h2>
            <button type="button" class="info-modal__close" data-info-close aria-label="<?= e(__('common.close')) ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                     stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                    <line x1="6" y1="18" x2="18" y2="6"></line>
                </svg>
            </button>
        </header>
        <div class="info-modal__body"><?= $infoBody /* trusted markup from the caller */ ?></div>
    </div>
</dialog>
