<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Config;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Services\ClaudeService;

/**
 * Admin → Settings. One section per form; each saves independently.
 * Sensitive values are stored encrypted via Config::set(..., 'encrypted').
 * Every change is recorded in the audit log (key name only, never the value).
 */
final class SettingsController
{
    private const SECTIONS = [
        'general', 'claude', 'translate', 'email', 'apis',
        'security', 'reviews', 'files', 'languages', 'fulltext', 'about',
    ];

    public function index(): void
    {
        redirect('/admin/settings/general');
    }

    public function show(string $section): void
    {
        if (!in_array($section, self::SECTIONS, true)) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            return;
        }

        echo View::render("admin/settings/{$section}", [
            'activeSection' => $section,
        ], 'layouts/admin');
    }

    public function save(string $section): void
    {
        match ($section) {
            'general'   => $this->saveGeneral(),
            'claude'    => $this->saveClaude(),
            'translate' => $this->saveTranslate(),
            'email'     => $this->saveEmail(),
            'apis'      => $this->saveApis(),
            'security'  => $this->saveSecurity(),
            'reviews'   => $this->saveReviews(),
            'files'     => $this->saveFiles(),
            'languages' => $this->saveLanguages(),
            'fulltext'  => $this->saveFulltext(),
            'about'     => $this->saveAbout(),
            default     => null,
        };

        Session::flash('admin_success', __('admin.saved'));
        redirect('/admin/settings/' . $section);
    }

    public function verifyClaude(): void
    {
        $result = ClaudeService::fromSettings()->verifyConnection();
        ActivityLog::record('settings.claude.verify', ['ok' => $result['ok']]);
        Session::flash($result['ok'] ? 'admin_success' : 'admin_error', $result['message']);
        redirect('/admin/settings/claude');
    }

    private function saveGeneral(): void
    {
        Config::set('site.name', trim((string) ($_POST['site_name'] ?? 'SysRevAI')) ?: 'SysRevAI', 'string', 'general', true);
        $locale = in_array(($_POST['default_locale'] ?? 'ca'), ['ca', 'es', 'en'], true) ? $_POST['default_locale'] : 'ca';
        Config::set('app.locale', $locale, 'string', 'general', true);
        Config::set('app.timezone', trim((string) ($_POST['timezone'] ?? 'Europe/Madrid')) ?: 'Europe/Madrid', 'string', 'general');
        Config::set('ui.theme', in_array(($_POST['theme'] ?? 'light'), ['light', 'dark', 'auto'], true) ? $_POST['theme'] : 'light', 'string', 'general', true);

        ActivityLog::record('settings.general.updated');
    }

    private function saveClaude(): void
    {
        // Only overwrite the key when a new non-empty value is submitted.
        $newKey = trim((string) ($_POST['api_key'] ?? ''));
        if ($newKey !== '') {
            Config::set('claude.api_key', $newKey, 'encrypted', 'integrations');
        }

        $models = ['claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'];
        $complex = in_array(($_POST['model_complex'] ?? ''), $models, true) ? $_POST['model_complex'] : 'claude-opus-4-7';
        $light   = in_array(($_POST['model_light'] ?? ''), $models, true) ? $_POST['model_light'] : 'claude-haiku-4-5-20251001';
        Config::set('claude.model_complex', $complex, 'string', 'integrations');
        Config::set('claude.model_light', $light, 'string', 'integrations');

        Config::set('claude.max_tokens', max(1, (int) ($_POST['max_tokens'] ?? 4096)), 'int', 'integrations');
        Config::set('claude.monthly_limit_usd', max(0, (int) ($_POST['monthly_limit_usd'] ?? 0)), 'int', 'integrations');

        foreach (['summaries', 'screening', 'extraction', 'bias', 'chat', 'dedup', 'copilot'] as $feature) {
            Config::set('claude.feature.' . $feature, !empty($_POST['feature'][$feature]), 'bool', 'integrations');
        }

        ActivityLog::record('settings.claude.updated', ['key_changed' => $newKey !== '']);
    }

    private function saveSecurity(): void
    {
        Config::set('security.min_password_length', max(8, (int) ($_POST['min_password_length'] ?? 12)), 'int', 'security');
        Config::set('security.max_login_attempts', max(1, (int) ($_POST['max_login_attempts'] ?? 5)), 'int', 'security');
        Config::set('security.lockout_minutes', max(1, (int) ($_POST['lockout_minutes'] ?? 15)), 'int', 'security');
        Config::set('security.session_lifetime', max(5, (int) ($_POST['session_lifetime'] ?? 120)), 'int', 'security');
        Config::set('security.force_https', !empty($_POST['force_https']), 'bool', 'security');
        $tfa = in_array(($_POST['two_factor_mode'] ?? 'optional'), ['disabled', 'optional', 'required'], true) ? $_POST['two_factor_mode'] : 'optional';
        Config::set('security.two_factor_mode', $tfa, 'string', 'security');

        ActivityLog::record('settings.security.updated');
    }

    private function saveAbout(): void
    {
        // The About panel is read-only (no settings to persist).
        ActivityLog::record('settings.about.updated');
    }

    private function saveTranslate(): void
    {
        Config::set('google.project_id', trim((string) ($_POST['project_id'] ?? '')), 'string', 'integrations');
        Config::set('google.active_languages', $this->multiSelect('active_languages', ['ca', 'es', 'en', 'fr', 'de', 'pt', 'it']), 'json', 'integrations');
        Config::set('google.cache_enabled', !empty($_POST['cache_enabled']), 'bool', 'integrations');
        Config::set('google.cache_ttl_days', max(1, (int) ($_POST['cache_ttl_days'] ?? 90)), 'int', 'integrations');

        $stored = $this->storeServiceAccount();
        if ($stored !== null) {
            Config::set('google.credentials_path', $stored, 'string', 'integrations');
        }

        ActivityLog::record('settings.translate.updated', ['credentials_uploaded' => $stored !== null]);
    }

    private function saveEmail(): void
    {
        Config::set('smtp.host', trim((string) ($_POST['host'] ?? '')), 'string', 'integrations');
        Config::set('smtp.port', max(1, (int) ($_POST['port'] ?? 587)), 'int', 'integrations');
        Config::set('smtp.username', trim((string) ($_POST['username'] ?? '')), 'string', 'integrations');
        $pw = (string) ($_POST['password'] ?? '');
        if ($pw !== '') {
            Config::set('smtp.password', $pw, 'encrypted', 'integrations');
        }
        $enc = in_array(($_POST['encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true) ? $_POST['encryption'] : 'tls';
        Config::set('smtp.encryption', $enc, 'string', 'integrations');
        Config::set('smtp.from_email', trim((string) ($_POST['from_email'] ?? '')), 'string', 'integrations');
        Config::set('smtp.from_name', trim((string) ($_POST['from_name'] ?? 'SysRevAI')), 'string', 'integrations');

        foreach (['conflict', 'invitation', 'error', 'weekly'] as $event) {
            Config::set('notify.' . $event, !empty($_POST['notify'][$event]), 'bool', 'integrations');
        }
        ActivityLog::record('settings.email.updated', ['password_changed' => $pw !== '']);
    }

    private function saveApis(): void
    {
        $pubmed = trim((string) ($_POST['pubmed_api_key'] ?? ''));
        if ($pubmed !== '') {
            Config::set('pubmed.api_key', $pubmed, 'encrypted', 'integrations');
        }
        Config::set('pubmed.enabled', !empty($_POST['pubmed_enabled']), 'bool', 'integrations');

        Config::set('crossref.email', trim((string) ($_POST['crossref_email'] ?? '')), 'string', 'integrations');
        Config::set('crossref.enabled', !empty($_POST['crossref_enabled']), 'bool', 'integrations');

        Config::set('unpaywall.email', trim((string) ($_POST['unpaywall_email'] ?? '')), 'string', 'integrations');
        Config::set('unpaywall.enabled', !empty($_POST['unpaywall_enabled']), 'bool', 'integrations');

        Config::set('openalex.enabled', !empty($_POST['openalex_enabled']), 'bool', 'integrations');

        $ss = trim((string) ($_POST['semantic_scholar_api_key'] ?? ''));
        if ($ss !== '') {
            Config::set('semantic_scholar.api_key', $ss, 'encrypted', 'integrations');
        }
        Config::set('semantic_scholar.enabled', !empty($_POST['semantic_scholar_enabled']), 'bool', 'integrations');

        ActivityLog::record('settings.apis.updated');
    }

    private function saveReviews(): void
    {
        $reasons = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) ($_POST['default_exclusion_reasons'] ?? '')) ?: []
        )));
        Config::set('reviews.default_exclusion_reasons', $reasons, 'json', 'reviews');
        Config::set('reviews.rob_tools', $this->multiSelect('rob_tools', ['rob2', 'robins_i', 'newcastle_ottawa', 'jbi']), 'json', 'reviews');
        Config::set('reviews.double_reviewer_default', !empty($_POST['double_reviewer_default']), 'bool', 'reviews');
        $cr = in_array(($_POST['conflict_resolution'] ?? 'manual'), ['manual', 'third'], true) ? $_POST['conflict_resolution'] : 'manual';
        Config::set('reviews.conflict_resolution', $cr, 'string', 'reviews');

        ActivityLog::record('settings.reviews.updated');
    }

    private function saveFiles(): void
    {
        Config::set('files.max_pdf_mb', max(1, (int) ($_POST['max_pdf_mb'] ?? 50)), 'int', 'files');
        Config::set('files.allowed_types', $this->multiSelect('allowed_types', ['pdf', 'doc', 'docx', 'txt']), 'json', 'files');
        $loc = in_array(($_POST['upload_location'] ?? 'docroot'), ['docroot', 'outside'], true) ? $_POST['upload_location'] : 'docroot';
        Config::set('files.upload_location', $loc, 'string', 'files');
        Config::set('files.compress', !empty($_POST['compress']), 'bool', 'files');
        Config::set('files.ocr', !empty($_POST['ocr']), 'bool', 'files');

        ActivityLog::record('settings.files.updated');
    }

    private function saveLanguages(): void
    {
        $supported = (array) config('supported_locales', ['ca', 'es', 'en']);
        $active = array_values(array_intersect($this->multiSelect('active_locales', $supported), $supported));
        if ($active === []) {
            $active = ['ca'];
        }
        Config::set('ui.active_locales', $active, 'json', 'general', true);
        ActivityLog::record('settings.languages.updated');
    }

    private function saveFulltext(): void
    {
        Config::set('fulltext.enabled', !empty($_POST['enabled']), 'bool', 'fulltext');
        $mode = in_array(($_POST['mode'] ?? 'manual'), ['manual', 'on_import', 'scheduled'], true) ? $_POST['mode'] : 'manual';
        Config::set('fulltext.mode', $mode, 'string', 'fulltext');
        Config::set('fulltext.concurrency', max(1, min(10, (int) ($_POST['concurrency'] ?? 3))), 'int', 'fulltext');
        Config::set('fulltext.timeout_seconds', max(5, (int) ($_POST['timeout_seconds'] ?? 60)), 'int', 'fulltext');
        Config::set('fulltext.retry_after_days', max(1, (int) ($_POST['retry_after_days'] ?? 30)), 'int', 'fulltext');
        Config::set('fulltext.exhaustive', !empty($_POST['exhaustive']), 'bool', 'fulltext');
        Config::set('fulltext.download_immediately', !empty($_POST['download_immediately']), 'bool', 'fulltext');
        Config::set('fulltext.polite_email', trim((string) ($_POST['polite_email'] ?? '')), 'string', 'fulltext');

        // Priority order — accept either an ordered text field (one source per line) or a hidden CSV.
        $known = array_keys(\SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService::SOURCE_MAP);
        $raw   = (string) ($_POST['priority'] ?? '');
        $items = array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: []));
        $items = array_values(array_intersect($items, $known));
        if ($items === []) {
            $items = \SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService::DEFAULT_PRIORITY;
        }
        Config::set('fulltext.priority', $items, 'json', 'fulltext');

        // Per-source configuration.
        foreach ($known as $name) {
            Config::set('fulltext.' . $name . '.enabled', !empty($_POST['sources'][$name]['enabled']), 'bool', 'fulltext');
            if (isset($_POST['sources'][$name]['email'])) {
                Config::set('fulltext.' . $name . '.email', trim((string) $_POST['sources'][$name]['email']), 'string', 'fulltext');
            }
        }

        // Optional API keys — only overwrite when the field is non-empty.
        foreach (['pmc', 'semantic_scholar'] as $keySource) {
            $key = trim((string) ($_POST['sources'][$keySource]['api_key'] ?? ''));
            if ($key !== '') {
                Config::set('fulltext.' . $keySource . '.api_key', $key, 'encrypted', 'fulltext');
            }
        }

        ActivityLog::record('settings.fulltext.updated');
    }

    /* ── Integration actions ───────────────────────────────────────────── */

    /** Verify a single full-text source (JSON response for AJAX). */
    public function verifyFulltextSource(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = (string) ($_POST['source'] ?? '');
        $map  = \SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService::SOURCE_MAP;
        if (!isset($map[$name])) {
            echo json_encode(['ok' => false, 'message' => 'Unknown source.']);
            return;
        }
        $instance = new $map[$name]();
        $check = $instance->verifyConnection();
        ActivityLog::record('settings.fulltext.verify', ['source' => $name, 'ok' => (bool) $check['ok']]);
        echo json_encode([
            'ok'      => (bool) $check['ok'],
            'message' => (string) $check['message'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Run the full chain for a test DOI/PMID (JSON response with the trace). */
    public function testFulltextChain(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $doi  = trim((string) ($_POST['doi'] ?? '')) ?: null;
        $pmid = trim((string) ($_POST['pmid'] ?? '')) ?: null;
        if ($doi === null && $pmid === null) {
            echo json_encode(['ok' => false, 'error' => 'no_identifier']);
            return;
        }
        $service = new \SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService();
        $result = $service->testChain($doi, $pmid);
        ActivityLog::record('settings.fulltext.test', ['doi' => $doi, 'pmid' => $pmid, 'success' => $result['success']]);
        echo json_encode([
            'ok'       => true,
            'success'  => $result['success'],
            'attempts' => $result['attempts'],
            'result'   => $result['result'] === null ? null : [
                'source'        => $result['result']->source,
                'pdf_url'       => $result['result']->pdfUrl,
                'license_type'  => $result['result']->licenseType,
                'version'       => $result['result']->version,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function sendTestEmail(): void
    {
        $to = trim((string) ($_POST['test_email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('admin_error', __('admin.email.test_invalid'));
            redirect('/admin/settings/email');
        }
        $result = \SysRevAI\Services\MailService::sendTest($to);
        ActivityLog::record('settings.email.test', ['ok' => $result['ok']]);
        Session::flash($result['ok'] ? 'admin_success' : 'admin_error', $result['message']);
        redirect('/admin/settings/email');
    }

    public function verifyTranslate(): void
    {
        $result = \SysRevAI\Services\TranslateService::verifyConfig();
        Session::flash($result['ok'] ? 'admin_success' : 'admin_error', $result['message']);
        redirect('/admin/settings/translate');
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /** Read a checkbox/multi-select POST array, keeping only allowed values. */
    private function multiSelect(string $field, array $allowed): array
    {
        $raw = $_POST[$field] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_intersect(array_map('strval', $raw), $allowed));
    }

    /**
     * Securely store an uploaded Google service-account JSON outside the
     * docroot (0600). Returns the stored path or null if no valid file.
     */
    private function storeServiceAccount(): ?string
    {
        if (empty($_FILES['service_account']['tmp_name']) || !is_uploaded_file($_FILES['service_account']['tmp_name'])) {
            return null;
        }
        $contents = (string) file_get_contents($_FILES['service_account']['tmp_name']);
        $decoded = json_decode($contents, true);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'service_account') {
            Session::flash('admin_error', __('admin.translate.invalid_json'));
            return null;
        }
        $dir = (string) config('paths.credentials');
        $path = $dir . '/google_sa.json';
        if (@file_put_contents($path, $contents) === false) {
            return null;
        }
        @chmod($path, 0600);
        return $path;
    }

}
