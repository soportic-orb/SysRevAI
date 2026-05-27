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
    private const SECTIONS = ['general', 'claude', 'security', 'about'];

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
            'general'  => $this->saveGeneral(),
            'claude'   => $this->saveClaude(),
            'security' => $this->saveSecurity(),
            'about'    => $this->saveAbout(),
            default    => null,
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
        Config::set('ui.accent_color', $this->sanitizeHex((string) ($_POST['accent_color'] ?? '#0072b2')), 'string', 'general', true);
        Config::set('ui.theme', in_array(($_POST['theme'] ?? 'light'), ['light', 'dark', 'auto'], true) ? $_POST['theme'] : 'light', 'string', 'general', true);
        Config::set('site.footer_text', trim((string) ($_POST['footer_text'] ?? '')), 'string', 'general', true);
        Config::set('site.show_branding', !empty($_POST['show_branding']), 'bool', 'general', true);

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

        $temp = (float) ($_POST['temperature'] ?? 0.2);
        $temp = max(0.0, min(1.0, $temp));
        Config::set('claude.temperature', (string) $temp, 'string', 'integrations');
        Config::set('claude.max_tokens', max(1, (int) ($_POST['max_tokens'] ?? 4096)), 'int', 'integrations');
        Config::set('claude.monthly_limit_usd', max(0, (int) ($_POST['monthly_limit_usd'] ?? 0)), 'int', 'integrations');

        foreach (['summaries', 'screening', 'extraction', 'bias', 'chat', 'dedup'] as $feature) {
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
        Config::set('about.show_dashboard_mention', !empty($_POST['show_dashboard_mention']), 'bool', 'about', true);
        ActivityLog::record('settings.about.updated');
    }

    private function sanitizeHex(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '#0072b2';
    }
}
