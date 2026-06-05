<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Config;
use SysRevAI\Core\I18n;
use SysRevAI\Core\Session;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\TranslationOverride;

/**
 * Admin → Languages editor. Backs the in-DB string-override flow:
 *
 *   - save():        bulk upsert / delete overrides for the active locale
 *                     and group filter shown on the page.
 *   - addLocale():   register a new locale code (with display name) so it
 *                     joins the "active in UI" pool. Empty by default —
 *                     the admin then fills it via the editor.
 *   - removeLocale(): drop a custom locale and every override that belonged
 *                     to it. Refuses on locales hard-coded in config.php.
 *
 * Every action is recorded in the activity log; the post-save redirect
 * preserves the locale + group selection so the admin can keep editing.
 */
final class LanguagesController
{
    public function save(): void
    {
        $locale = trim((string) ($_POST['locale'] ?? ''));
        $group  = trim((string) ($_POST['group'] ?? ''));
        if (!self::isKnownLocale($locale)) {
            Session::flash('admin_error', __('admin.languages.unknown_locale'));
            redirect('/admin/settings/languages');
        }

        $values = (array) ($_POST['values'] ?? []);
        $userId = (int) Auth::id();
        $defaults = I18n::fileMap($locale === I18n::locale() ? $locale : $locale);
        // Fallback locale defaults are what we compare against when the
        // locale itself ships no value (e.g. a brand-new custom locale).
        $fallbackDefaults = I18n::fileMap('ca');

        $upserted = 0;
        $deleted  = 0;
        foreach ($values as $key => $val) {
            $key = (string) $key;
            if ($group !== '' && !str_starts_with($key, $group . '.') && $key !== $group) {
                // Defence in depth — the form only submits keys from the
                // displayed group, but we don't trust the browser.
                continue;
            }
            $val = (string) $val;
            $base = $defaults[$key] ?? $fallbackDefaults[$key] ?? null;

            // Empty submission OR identical to the platform default → drop
            // the override so the platform default takes back over.
            if (trim($val) === '' || ($base !== null && $val === $base)) {
                if (TranslationOverride::delete($locale, $key) > 0) {
                    $deleted++;
                }
                continue;
            }
            TranslationOverride::upsert($locale, $key, $val, $userId);
            $upserted++;
        }

        I18n::flushOverrides($locale);
        ActivityLog::record('settings.languages.overrides_saved', [
            'locale'   => $locale,
            'group'    => $group,
            'upserted' => $upserted,
            'deleted'  => $deleted,
        ]);
        Session::flash('admin_success', __('admin.languages.overrides_saved', $upserted, $deleted));
        redirect('/admin/settings/languages?' . http_build_query([
            'locale' => $locale,
            'group'  => $group,
        ]));
    }

    public function addLocale(): void
    {
        $code = strtolower(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));

        // ISO 639-1 / 639-2 sized; the storage column caps at 8 anyway.
        if (!preg_match('/^[a-z]{2,3}([_-][a-z]{2,4})?$/i', $code)) {
            Session::flash('admin_error', __('admin.languages.bad_code'));
            redirect('/admin/settings/languages');
        }
        if ($name === '') {
            Session::flash('admin_error', __('admin.languages.bad_name'));
            redirect('/admin/settings/languages');
        }
        $code = strtolower($code);

        // Hard-coded codes already ship with content; the admin can edit
        // them but mustn't be able to "re-create" them as customs.
        $config = (array) config('supported_locales', []);
        if (in_array($code, $config, true)) {
            Session::flash('admin_error', __('admin.languages.already_supported'));
            redirect('/admin/settings/languages');
        }

        $custom = (array) (setting('ui.custom_locales') ?? []);
        $custom[$code] = $name;
        Config::set('ui.custom_locales', $custom, 'json', 'general', true);

        ActivityLog::record('settings.languages.locale_added', ['code' => $code, 'name' => $name]);
        Session::flash('admin_success', __('admin.languages.locale_added', $name));
        redirect('/admin/settings/languages?' . http_build_query(['locale' => $code]));
    }

    public function removeLocale(): void
    {
        $code = strtolower(trim((string) ($_POST['code'] ?? '')));
        $config = (array) config('supported_locales', []);
        if (in_array($code, $config, true)) {
            Session::flash('admin_error', __('admin.languages.cant_remove_builtin'));
            redirect('/admin/settings/languages');
        }
        $custom = (array) (setting('ui.custom_locales') ?? []);
        if (!array_key_exists($code, $custom)) {
            redirect('/admin/settings/languages');
        }
        unset($custom[$code]);
        Config::set('ui.custom_locales', $custom, 'json', 'general', true);

        $wiped = TranslationOverride::clearLocale($code);
        I18n::flushOverrides($code);

        // Also drop it from the active-UI list so the language menu refreshes.
        $active = (array) (setting('ui.active_locales') ?? ['ca', 'es', 'en']);
        $active = array_values(array_filter($active, static fn (string $c): bool => $c !== $code));
        Config::set('ui.active_locales', $active === [] ? ['ca'] : $active, 'json', 'general', true);

        ActivityLog::record('settings.languages.locale_removed', ['code' => $code, 'overrides_wiped' => $wiped]);
        Session::flash('admin_success', __('admin.languages.locale_removed', $code));
        redirect('/admin/settings/languages');
    }

    private static function isKnownLocale(string $code): bool
    {
        return in_array($code, I18n::allowedLocales(), true);
    }
}
