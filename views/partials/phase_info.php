<?php

declare(strict_types=1);

/**
 * Render the small "i" trigger + the info modal for one of the four
 * review phases (screening / fulltext / extraction / rob). Each phase
 * has matching info_* lang keys; we read them and build the modal
 * body so each view stays short.
 *
 * Required:
 *   $phaseKey  — one of: screening, fulltext, extraction, rob
 */

$phaseKey = (string) ($phaseKey ?? '');
$prefix   = match ($phaseKey) {
    'screening'  => 'screening',
    'fulltext'   => 'fulltext',
    'extraction' => 'extraction',
    'rob'        => 'rob',
    default      => null,
};
if ($prefix === null) {
    return;
}

$infoModalId    = 'infoModal_' . $prefix;
$infoTitle      = (string) __($prefix . '.title');
$infoButtonAria = (string) __('common.info_about', $infoTitle);

$bullets = static function (string $key): string {
    $raw = (string) __($key);
    $items = array_filter(array_map('trim', explode('|', $raw)));
    if (count($items) <= 1) {
        return '<p>' . e($raw) . '</p>';
    }
    $out = '<ul>';
    foreach ($items as $it) {
        $out .= '<li>' . e($it) . '</li>';
    }
    return $out . '</ul>';
};

$infoBody = '<h3>' . e(__($prefix . '.info_what_label')) . '</h3>'
          . '<p>' . e(__($prefix . '.info_what')) . '</p>'
          . '<h3>' . e(__($prefix . '.info_how_label')) . '</h3>'
          . '<p>' . e(__($prefix . '.info_how')) . '</p>'
          . '<h3>' . e(__($prefix . '.info_best_label')) . '</h3>'
          . $bullets($prefix . '.info_best');

require __DIR__ . '/info_modal.php';
