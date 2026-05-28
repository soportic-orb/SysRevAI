<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\View;

/**
 * Public "About" page reachable without login. Shows version, license, repo
 * link, contributors and the donation card. Per project policy this page is
 * one of the discreet, non-intrusive places the donation link appears.
 */
final class AboutController
{
    public function show(): void
    {
        require_once config('paths.base') . '/config/donate.php';
        echo View::render('about/show', [
            'version' => (string) config('app.version', '0.1.0-dev'),
        ], 'layouts/auth');
    }
}
