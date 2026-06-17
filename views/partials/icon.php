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
    // Tabler "vocabulary" — open notebook spread, reads as a stack of
    // reviews at a glance and keeps the same trace weight as the rest
    // of the topbar icons.
    'reviews'   => '<path d="M10 19h-6a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1h6a2 2 0 0 1 2 2a2 2 0 0 1 2 -2h6a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-6a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2"></path>'
                 . '<path d="M12 5v16"></path>'
                 . '<path d="M7 7h1"></path><path d="M7 11h1"></path>'
                 . '<path d="M16 7h1"></path><path d="M16 11h1"></path><path d="M16 15h1"></path>',
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
    // Tabler "users-group" — three-person huddle. Used as the icon-only
    // tab label on the article action bar.
    'team_group' => '<path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>'
                 . '<path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1"></path>'
                 . '<path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>'
                 . '<path d="M17 10h2a2 2 0 0 1 2 2v1"></path>'
                 . '<path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>'
                 . '<path d="M3 13v-1a2 2 0 0 1 2 -2h2"></path>',
    // Tabler "file-export" — page with an outward arrow. Used as the
    // icon-only "Export" tab on the article action bar.
    'file_export' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"></path>'
                 . '<path d="M11.5 21h-4.5a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v5m-5 6h7m-3 -3l3 3l-3 3"></path>',
    // Tabler "download" — tray with a downward arrow. Used as the
    // icon-only "Download original" button.
    'download'  => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"></path>'
                 . '<path d="M7 11l5 5l5 -5"></path>'
                 . '<path d="M12 4l0 12"></path>',
    'protocol'  => '<path d="M14.5 4.5 19 9l-9.5 9.5H5V14L14.5 4.5Z"></path>'
                 . '<path d="m12.5 6.5 5 5"></path>',
    'archive'   => '<rect x="3" y="4" width="18" height="4" rx="1"></rect>'
                 . '<path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>'
                 . '<path d="M10 12h4"></path>',
    // Tabler trash — bin with lid and two inner bars, used by destructive
    // actions like permanently deleting an archived review.
    'trash'     => '<path d="M4 7h16"></path>'
                 . '<path d="M10 11v6"></path>'
                 . '<path d="M14 11v6"></path>'
                 . '<path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>'
                 . '<path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>',
    // Tabler "trash-x" — trash bin with an X mark inside, used as the
    // icon-only "Delete article" button.
    'trash_x'   => '<path d="M4 7h16"></path>'
                 . '<path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>'
                 . '<path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>'
                 . '<path d="M10 12l4 4m0 -4l-4 4"></path>',
    // Tabler "book-download" — full-text retrieval action button on
    // the references table.
    'book_download' => '<path d="M12 20h-6a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12v5"></path>'
                 . '<path d="M13 16h-7a2 2 0 0 0 -2 2"></path>'
                 . '<path d="M15 19l3 3l3 -3"></path>'
                 . '<path d="M18 22v-9"></path>',
    // Tabler "text-wrap-disabled" — represents the AI summary action.
    'text_wrap'     => '<path d="M4 6l10 0"></path>'
                 . '<path d="M4 18l10 0"></path>'
                 . '<path d="M4 12h17l-3 -3m0 6l3 -3"></path>',
    // Tabler "analyze" — represents the peer-review rubric action.
    'analyze'       => '<path d="M20 11a8.1 8.1 0 0 0 -6.986 -6.918a8.095 8.095 0 0 0 -8.019 3.918"></path>'
                 . '<path d="M4 13a8.1 8.1 0 0 0 15 3"></path>'
                 . '<path d="M18 16a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>'
                 . '<path d="M4 8a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>'
                 . '<path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path>',
    // Tabler "language" — translation action on Summary / Peer-review.
    'language'      => '<path d="M9 6.371c0 4.418 -2.239 6.629 -5 6.629"></path>'
                 . '<path d="M4 6.371h7"></path>'
                 . '<path d="M5 9c0 2.144 2.252 3.908 6 4"></path>'
                 . '<path d="M12 20l4 -9l4 9"></path>'
                 . '<path d="M19.1 18h-6.2"></path>'
                 . '<path d="M6.694 3l.793 .582"></path>',
    // Document page with text lines — used to mark references that
    // already carry an abstract on the references table.
    'abstract'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>'
                 . '<polyline points="14 3 14 8 19 8"></polyline>'
                 . '<line x1="8" y1="13" x2="16" y2="13"></line>'
                 . '<line x1="8" y1="17" x2="16" y2="17"></line>'
                 . '<line x1="8" y1="9"  x2="11" y2="9"></line>',

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
