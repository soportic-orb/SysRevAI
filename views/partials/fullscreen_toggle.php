<?php

declare(strict_types=1);

/**
 * Fullscreen toggle — sits in the topbar to the left of the theme
 * switch. Uses the Fullscreen API on document.documentElement. The
 * `body.is-fullscreen` class is flipped from JS so the two SVGs
 * (maximize / minimize) can swap with CSS rules instead of inline
 * style juggling.
 */
?>
<button type="button" class="fullscreen-toggle" id="fullscreenToggle"
        aria-label="<?= e(__('nav.fullscreen_toggle')) ?>"
        title="<?= e(__('nav.fullscreen_enter')) ?>"
        aria-pressed="false">
    <svg class="fullscreen-toggle__icon fullscreen-toggle__icon--enter" viewBox="0 0 24 24"
         width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 9V5a1 1 0 0 1 1-1h4"></path>
        <path d="M20 9V5a1 1 0 0 0-1-1h-4"></path>
        <path d="M4 15v4a1 1 0 0 0 1 1h4"></path>
        <path d="M20 15v4a1 1 0 0 1-1 1h-4"></path>
    </svg>
    <svg class="fullscreen-toggle__icon fullscreen-toggle__icon--exit" viewBox="0 0 24 24"
         width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M9 4v4a1 1 0 0 1-1 1H4"></path>
        <path d="M15 4v4a1 1 0 0 0 1 1h4"></path>
        <path d="M9 20v-4a1 1 0 0 0-1-1H4"></path>
        <path d="M15 20v-4a1 1 0 0 1 1-1h4"></path>
    </svg>
</button>
<script>
(function () {
    'use strict';
    var btn = document.getElementById('fullscreenToggle');
    if (!btn) return;

    var root = document.documentElement;
    var labels = {
        enter: <?= json_encode(__('nav.fullscreen_enter')) ?>,
        exit:  <?= json_encode(__('nav.fullscreen_exit')) ?>
    };

    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function apply() {
        var on = isFullscreen();
        document.body.classList.toggle('is-fullscreen', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? labels.exit : labels.enter;
        btn.setAttribute('aria-label', on ? labels.exit : labels.enter);
    }

    btn.addEventListener('click', function () {
        if (isFullscreen()) {
            (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
        } else {
            (root.requestFullscreen || root.webkitRequestFullscreen || function () {}).call(root);
        }
    });

    /* The browser's own UI (Esc, F11) can leave / enter fullscreen too,
       so always reflect the live state instead of trusting the click. */
    document.addEventListener('fullscreenchange', apply);
    document.addEventListener('webkitfullscreenchange', apply);
    apply();
})();
</script>
