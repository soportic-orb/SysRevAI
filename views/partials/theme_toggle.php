<?php

declare(strict_types=1);

/**
 * Theme toggle (sun / moon) — sits in the topbar, left of the notification
 * bell. Reads `data-theme` from <html> and flips between "light" / "dark"
 * on click, persisting the choice to localStorage so it survives reloads
 * and follows the user across pages.
 *
 * The actual boot-time theme is applied by the small script in the layout
 * <head> so the page never flashes the wrong palette before this button
 * is wired up.
 */
?>
<button type="button" class="theme-toggle" id="themeToggle"
        aria-label="<?= e(__('nav.theme_toggle')) ?>"
        title="<?= e(__('nav.theme_toggle')) ?>">
    <svg class="theme-toggle__icon theme-toggle__icon--sun" viewBox="0 0 24 24" width="20" height="20"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true">
        <circle cx="12" cy="12" r="4"></circle>
        <line x1="12" y1="2"  x2="12" y2="5"></line>
        <line x1="12" y1="19" x2="12" y2="22"></line>
        <line x1="2"  y1="12" x2="5"  y2="12"></line>
        <line x1="19" y1="12" x2="22" y2="12"></line>
        <line x1="4.6"  y1="4.6"  x2="6.7"  y2="6.7"></line>
        <line x1="17.3" y1="17.3" x2="19.4" y2="19.4"></line>
        <line x1="4.6"  y1="19.4" x2="6.7"  y2="17.3"></line>
        <line x1="17.3" y1="6.7"  x2="19.4" y2="4.6"></line>
    </svg>
    <svg class="theme-toggle__icon theme-toggle__icon--moon" viewBox="0 0 24 24" width="20" height="20"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true">
        <path d="M21 12.8A8.5 8.5 0 0 1 11.2 3a7 7 0 1 0 9.8 9.8Z"></path>
    </svg>
</button>
