<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\View;

/**
 * Public-facing Privacy Policy and Terms of Use pages. The text lives in
 * the settings table (legal.privacy_policy / legal.terms_of_use) as raw
 * HTML so admins can paste rich content. When the setting is empty the
 * view shows a friendly "not yet published" placeholder.
 */
final class LegalController
{
    public function privacy(): void
    {
        $this->render(
            'privacy',
            __('legal.privacy_title'),
            (string) (setting('legal.privacy_policy') ?? '')
        );
    }

    public function terms(): void
    {
        $this->render(
            'terms',
            __('legal.terms_title'),
            (string) (setting('legal.terms_of_use') ?? '')
        );
    }

    private function render(string $page, string $title, string $bodyHtml): void
    {
        echo View::render('legal/page', [
            'page'     => $page,
            'title'    => $title,
            'bodyHtml' => $bodyHtml,
        ]);
    }
}
