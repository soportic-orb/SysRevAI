<?php

declare(strict_types=1);

/**
 * Render a small user avatar. Falls back to a coloured initials badge when
 * the user hasn't uploaded a picture yet, so the UI always has something
 * meaningful to show (no broken-image icons).
 *
 * Required:
 *   $avatarUser  — user array (must have name, may have email + avatar_path)
 * Optional:
 *   $avatarSize  — pixel size of the rendered square (default 28)
 *   $avatarClass — extra CSS class on the wrapping element
 */

$_au   = $avatarUser ?? null;
$_size = (int) ($avatarSize ?? 28);
$_cls  = (string) ($avatarClass ?? '');
if (!is_array($_au)) {
    return;
}
$_path = (string) ($_au['avatar_path'] ?? '');
$_name = trim((string) ($_au['name'] ?? ''));

if ($_path !== '') {
    echo '<img class="avatar ' . e($_cls) . '" '
        . 'src="' . e('/uploads/' . ltrim($_path, '/')) . '" '
        . 'alt="" '
        . 'width="' . $_size . '" height="' . $_size . '" '
        . 'style="width:' . $_size . 'px;height:' . $_size . 'px;border-radius:50%;object-fit:cover">';
    return;
}

// Initials fallback. Pick at most two leading letters and a deterministic
// background colour from the user's name so each person always shows up the
// same way.
$_words = preg_split('/\s+/', $_name) ?: [];
$_initials = '';
foreach ($_words as $_w) {
    if ($_w === '') continue;
    $_initials .= mb_strtoupper(mb_substr($_w, 0, 1, 'UTF-8'), 'UTF-8');
    if (mb_strlen($_initials, 'UTF-8') >= 2) break;
}
if ($_initials === '') {
    $_initials = '·';
}
$_hue = 0;
foreach (str_split(($_name !== '' ? $_name : 'sysrevai')) as $_ch) {
    $_hue = ($_hue + ord($_ch) * 7) % 360;
}
$_bg = sprintf('hsl(%d, 65%%, 45%%)', $_hue);
?>
<span class="avatar avatar--initials <?= e($_cls) ?>"
      aria-hidden="true"
      style="width:<?= $_size ?>px;height:<?= $_size ?>px;
             border-radius:50%;display:inline-flex;
             align-items:center;justify-content:center;
             background:<?= e($_bg) ?>;color:#fff;font-weight:700;
             font-size:<?= max(10, (int) round($_size * 0.42)) ?>px;
             line-height:1;letter-spacing:.02em">
    <?= e($_initials) ?>
</span>
