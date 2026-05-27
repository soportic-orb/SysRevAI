<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Reusable donation link partial
|------------------------------------------------------------------------------
| Usage:
|     $style = 'footer'; // footer | card | inline | button
|     require __DIR__ . '/donate_link.php';
|
| The URL is centralized in config/donate.php (DONATE_URL) — see that file for
| the project's donation policy. This partial NEVER renders a pop-up/modal and
| must not be placed on the login screen.
|
| Translatable label: pass $donateLabel, or it falls back to a sensible default.
*/

if (!defined('DONATE_URL')) {
    require dirname(__DIR__, 2) . '/config/donate.php';
}

/** @var string $style */
$style = $style ?? 'inline';

/** @var string $donateLabel */
$label = $donateLabel ?? 'Support the project';

$url    = DONATE_URL;
$attrs  = 'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . ' target="_blank" rel="noopener noreferrer"';
$heart  = '<span aria-hidden="true">&#10084;</span>';
$safeLbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

switch ($style) {
    case 'footer':
        // Tiny inline link beside copyright/version. Always present.
        echo '<a class="donate donate--footer" ' . $attrs . '>'
            . $heart . ' ' . $safeLbl . '</a>';
        break;

    case 'button':
        echo '<a class="btn btn--donate" ' . $attrs . '>'
            . $heart . ' ' . $safeLbl . '</a>';
        break;

    case 'card':
        // Larger block for the admin "About" section / public /about page.
        echo '<div class="donate-card">'
            . '<p class="donate-card__text">' . $safeLbl . '</p>'
            . '<a class="btn btn--donate" ' . $attrs . '>' . $heart . ' Donate</a>'
            . '</div>';
        break;

    case 'inline':
    default:
        echo '<a class="donate donate--inline" ' . $attrs . '>'
            . $heart . ' ' . $safeLbl . '</a>';
        break;
}
