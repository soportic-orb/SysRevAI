<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Services\LegalDocumentService;

/**
 * Admin → Legal documents. One editor per document type (privacy / terms);
 * each editor shows three language tabs (es / ca / en). A row can either
 * use the on-disk template (default) or hold a custom HTML version edited
 * here. The auth middleware enforces the owner/admin role; this controller
 * re-checks defensively.
 */
final class LegalSettingsController
{
    private const TYPES = ['privacy', 'terms'];

    public function edit(string $type): void
    {
        $this->requireAdmin();
        if (!in_array($type, self::TYPES, true)) {
            redirect('/admin/legal/privacy');
        }

        $service  = new LegalDocumentService();
        $versions = $service->getAllVersions($type);

        echo View::render('legal/admin_editor', [
            'activeSection' => 'legal',
            'type'          => $type,
            'title'         => $type === 'privacy'
                ? __('admin.legal.privacy_title')
                : __('admin.legal.terms_title'),
            'versions'      => $versions,
            'languages'     => LegalDocumentService::SUPPORTED_LANGUAGES,
        ], 'layouts/admin');
    }

    public function save(string $type, string $language): void
    {
        $this->requireAdmin();
        $this->guardTypeAndLanguage($type, $language);

        $content = (string) ($_POST['content'] ?? '');
        if (trim($content) === '') {
            Session::flash('admin_error', __('admin.legal.empty_content_error'));
            redirect('/admin/legal/' . $type . '#' . $language);
        }

        $clean = $this->sanitizeHtml($content);

        $service = new LegalDocumentService();
        $service->saveCustomVersion($type, $language, $clean, (int) Auth::id());

        ActivityLog::record('admin.legal.saved', [
            'doc_type' => $type,
            'language' => $language,
        ]);

        Session::flash('admin_success', __('admin.legal.saved_ok'));
        redirect('/admin/legal/' . $type . '#' . $language);
    }

    public function restore(string $type, string $language): void
    {
        $this->requireAdmin();
        $this->guardTypeAndLanguage($type, $language);

        $service = new LegalDocumentService();
        $service->restoreDefault($type, $language, (int) Auth::id());

        ActivityLog::record('admin.legal.restored', [
            'doc_type' => $type,
            'language' => $language,
        ]);

        Session::flash('admin_success', __('admin.legal.restored_ok'));
        redirect('/admin/legal/' . $type . '#' . $language);
    }

    /**
     * Strip everything that isn't on the legal-content allow-list.
     *
     * TODO: swap in HTMLPurifier for production deployments so that
     * attribute-level injection vectors (style="…", javascript: URLs) are
     * also stripped consistently. `strip_tags()` leaves attributes alone.
     */
    private function sanitizeHtml(string $html): string
    {
        $allowed = '<section><h1><h2><h3><h4><p><ul><ol><li>'
                 . '<strong><em><b><i><a><br><div><span>';
        $clean = strip_tags($html, $allowed);

        // Defensive pass: kill `on*` event-handler attributes and
        // javascript: URLs that strip_tags won't strip.
        $clean = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean);
        $clean = (string) preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'\s>]*\2/i', '$1="#"', $clean);
        return $clean;
    }

    private function requireAdmin(): void
    {
        if (!Auth::hasRole('owner', 'admin')) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
    }

    private function guardTypeAndLanguage(string $type, string $language): void
    {
        if (!in_array($type, self::TYPES, true)
            || !in_array($language, LegalDocumentService::SUPPORTED_LANGUAGES, true)) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            exit;
        }
    }
}
