<?php

declare(strict_types=1);

/**
 * Inline SVG icon catalogue. Caller sets $iconName (and optionally $iconClass)
 * before requiring this partial; we echo a single 16×16 SVG using
 * `stroke="currentColor"` so the icon inherits the link / button colour
 * and adapts automatically to the dark theme.
 *
 *   <?php $iconName = 'dashboard'; require '…/icon.php'; ?>
 *
 * Glyphs follow the Tabler / Feather stroke style (1.75 weight, round caps)
 * to stay visually consistent with the chevron icons already used by the
 * collapsible cards.
 *
 * @var string $iconName
 * @var string|null $iconClass  Extra CSS class (e.g. nav-icon).
 */

$path = match ($iconName ?? '') {
    // Main nav
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"></rect>'
                 . '<rect x="14" y="3" width="7" height="7" rx="1"></rect>'
                 . '<rect x="3" y="14" width="7" height="7" rx="1"></rect>'
                 . '<rect x="14" y="14" width="7" height="7" rx="1"></rect>',
    'reviews'   => '<path d="M9 5h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"></path>'
                 . '<path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"></path>'
                 . '<path d="M10 11h4"></path><path d="M10 15h4"></path>',
    'search'    => '<circle cx="11" cy="11" r="6"></circle>'
                 . '<path d="m20 20-3.5-3.5"></path>',
    'settings'  => '<circle cx="12" cy="12" r="2.6"></circle>'
                 . '<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 4.29 16.96l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82L4.21 7.12A2 2 0 1 1 7.04 4.29l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06A2 2 0 1 1 19.71 7.04l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.27.65.88 1.1 1.51 1.1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>',

    // Review sub-nav
    'export'    => '<path d="M12 3v12"></path>'
                 . '<path d="m7 10 5 5 5-5"></path>'
                 . '<path d="M4 17v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"></path>',
    'references' => '<path d="M8 6h12"></path><path d="M8 12h12"></path><path d="M8 18h12"></path>'
                 . '<circle cx="4" cy="6"  r="1"></circle>'
                 . '<circle cx="4" cy="12" r="1"></circle>'
                 . '<circle cx="4" cy="18" r="1"></circle>',
    'team'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>'
                 . '<circle cx="9" cy="7" r="4"></circle>'
                 . '<path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>'
                 . '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
    'protocol'  => '<path d="M14.5 4.5 19 9l-9.5 9.5H5V14L14.5 4.5Z"></path>'
                 . '<path d="m12.5 6.5 5 5"></path>',
    'archive'   => '<rect x="3" y="4" width="18" height="4" rx="1"></rect>'
                 . '<path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>'
                 . '<path d="M10 12h4"></path>',

    // Import-outcome indicators
    'check'     => '<polyline points="20 6 9 17 4 12"></polyline>',
    'x'         => '<line x1="6" y1="6" x2="18" y2="18"></line>'
                 . '<line x1="6" y1="18" x2="18" y2="6"></line>',

    // Euro currency symbol for the AI-cost badge in the review sub-nav.
    'euro'      => '<path d="M18.5 6.5A6 6 0 0 0 8 11h7"></path>'
                 . '<path d="M15 14H8a6 6 0 0 0 10.5 4.5"></path>'
                 . '<path d="M4 11h12"></path><path d="M4 14h12"></path>',

    // GitHub Octocat — Tabler-style simplified mark for the footer link.
    'github'    => '<path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2 4.6 4.6 0 0 0-1.3 3.2c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"></path>',

    default     => '',
};

if ($path === '') {
    return;
}

$cls = 'icon' . (isset($iconClass) && $iconClass !== null ? ' ' . $iconClass : '');
?>
<svg class="<?= e($cls) ?>" viewBox="0 0 24 24" width="16" height="16"
     fill="none" stroke="currentColor" stroke-width="1.75"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $path ?></svg>
<?php
// Reset so a later include with a different $iconName doesn't inherit the
// caller's previous override.
$iconClass = null;
