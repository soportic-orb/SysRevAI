<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\I18n;
use SysRevAI\Core\View;
use SysRevAI\Services\LegalDocumentService;

/**
 * Public Privacy Policy and Terms of Use pages. Language is picked by:
 *   1. ?lang=xx query string;
 *   2. the visitor's current locale;
 *   3. the site's default locale;
 *   4. English.
 *
 * Rendered HTML is built by LegalDocumentService — which substitutes the
 * placeholders and prefers an admin-edited custom version when one exists.
 */
final class LegalController
{
    public function privacy(): void
    {
        $this->render('privacy');
    }

    public function terms(): void
    {
        $this->render('terms');
    }

    private function render(string $docType): void
    {
        $language = $this->pickLanguage();
        $service  = new LegalDocumentService();
        $html     = $service->render($docType, $language);

        $title = $docType === 'privacy'
            ? __('legal.privacy_title')
            : __('legal.terms_title');

        echo View::render('legal/public_view', [
            'docType'  => $docType,
            'title'    => $title,
            'content'  => $html,
            'language' => $language,
        ], null);
    }

    private function pickLanguage(): string
    {
        $candidates = [
            (string) ($_GET['lang'] ?? ''),
            I18n::locale(),
            (string) (setting('app.locale') ?? config('app.locale', 'en')),
            LegalDocumentService::DEFAULT_LANGUAGE,
        ];
        foreach ($candidates as $c) {
            $c = strtolower(substr($c, 0, 2));
            if (in_array($c, LegalDocumentService::SUPPORTED_LANGUAGES, true)) {
                return $c;
            }
        }
        return LegalDocumentService::DEFAULT_LANGUAGE;
    }
}
