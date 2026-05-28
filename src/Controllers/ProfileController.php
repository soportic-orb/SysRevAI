<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Crypto;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Notification;
use SysRevAI\Models\User;
use SysRevAI\Services\AvatarStorage;
use SysRevAI\Services\Totp;

/**
 * "My profile" area: account details, password change, optional TOTP 2FA,
 * and the per-channel notification preferences. Each section is gated by
 * the `auth` middleware (see config/routes.php).
 */
final class ProfileController
{
    /* ── Profile (name / email / locale) ─────────────────────────────── */

    public function profile(): void
    {
        echo View::render('profile/profile', [
            'user'   => Auth::user(),
            'active' => 'profile',
        ]);
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $name   = trim((string) ($_POST['name'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));
        $locale = (string) ($_POST['locale'] ?? 'ca');
        if (!in_array($locale, (array) config('supported_locales', ['ca', 'es', 'en']), true)) {
            $locale = 'ca';
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('profile.invalid_data'));
            redirect('/profile');
        }

        // Email collisions with other accounts.
        $existing = User::findByEmail($email);
        if ($existing !== null && (int) $existing['id'] !== (int) $user['id']) {
            Session::flash('error', __('profile.email_taken'));
            redirect('/profile');
        }

        User::updateProfile((int) $user['id'], $name, $email, $locale);
        ActivityLog::record('profile.updated', ['email_changed' => $email !== $user['email']]);
        Session::flash('success', __('profile.saved'));
        redirect('/profile');
    }

    /* ── Avatar ──────────────────────────────────────────────────────── */

    public function uploadAvatar(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $result = AvatarStorage::store($_FILES['avatar'] ?? [], (int) $user['id']);
        if (!$result['ok']) {
            $msg = match ($result['error'] ?? '') {
                'no_file'            => __('profile.avatar_no_file'),
                'too_large'          => __('profile.avatar_too_large'),
                'unsupported_format' => __('profile.avatar_bad_format'),
                'cannot_create_dir', 'cannot_write' => __('profile.avatar_write_failed'),
                default              => __('profile.avatar_generic_error'),
            };
            Session::flash('error', $msg);
            redirect('/profile');
        }
        // Replace any previous file.
        if (!empty($user['avatar_path'])) {
            AvatarStorage::delete((string) $user['avatar_path']);
        }
        User::setAvatarPath((int) $user['id'], $result['path']);
        ActivityLog::record('profile.avatar_uploaded');
        Session::flash('success', __('profile.avatar_saved'));
        redirect('/profile');
    }

    public function deleteAvatar(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!empty($user['avatar_path'])) {
            AvatarStorage::delete((string) $user['avatar_path']);
            User::setAvatarPath((int) $user['id'], null);
            ActivityLog::record('profile.avatar_removed');
        }
        Session::flash('success', __('profile.avatar_removed'));
        redirect('/profile');
    }

    /* ── Password change ─────────────────────────────────────────────── */

    public function password(): void
    {
        echo View::render('profile/password', [
            'user'    => Auth::user(),
            'active'  => 'password',
            'minLen'  => max(8, (int) (setting('security.min_password_length') ?? 12)),
        ]);
    }

    public function savePassword(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            Session::flash('error', __('profile.password_wrong_current'));
            redirect('/profile/password');
        }
        if ($new !== $confirm) {
            Session::flash('error', __('profile.password_mismatch'));
            redirect('/profile/password');
        }
        $minLen = max(8, (int) (setting('security.min_password_length') ?? 12));
        if (!self::passwordMeetsPolicy($new, $minLen)) {
            Session::flash('error', __('profile.password_weak', $minLen));
            redirect('/profile/password');
        }

        User::updatePassword((int) $user['id'], password_hash($new, PASSWORD_ARGON2ID));
        ActivityLog::record('profile.password_changed');
        Session::flash('success', __('profile.password_saved'));
        redirect('/profile/password');
    }

    /* ── Two-factor authentication ───────────────────────────────────── */

    public function twoFactor(): void
    {
        $user = Auth::user();
        $enabled = !empty($user['two_factor_secret']);

        // Setup phase: a freshly generated secret stays in the session so
        // the verify POST can pick it up. Wiped once enabled or cancelled.
        $pending = $_SESSION['pending_2fa_secret'] ?? null;

        if (!$enabled && $pending === null) {
            // Auto-prepare a secret on first visit so the QR / key are ready.
            $pending = Totp::generateSecret();
            $_SESSION['pending_2fa_secret'] = $pending;
        }

        $mode = (string) (setting('security.two_factor_mode') ?? 'optional');

        echo View::render('profile/two_factor', [
            'user'        => $user,
            'active'      => 'two_factor',
            'enabled'     => $enabled,
            'mode'        => $mode,
            'secret'      => $pending,
            'secretGroups' => $pending ? Totp::formatSecret($pending) : '',
            'otpauth'     => $pending ? Totp::otpauthUri($pending, (string) $user['email'], (string) (setting('site.name') ?? 'SysRevAI')) : '',
        ]);
    }

    public function enableTwoFactor(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!empty($user['two_factor_secret'])) {
            Session::flash('error', __('profile.tfa_already_on'));
            redirect('/profile/two-factor');
        }
        $secret = (string) ($_SESSION['pending_2fa_secret'] ?? '');
        $code   = (string) ($_POST['code'] ?? '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            Session::flash('error', __('profile.tfa_bad_code'));
            redirect('/profile/two-factor');
        }

        User::setTwoFactorSecret((int) $user['id'], Crypto::encrypt($secret));
        unset($_SESSION['pending_2fa_secret']);
        ActivityLog::record('profile.tfa_enabled');
        Session::flash('success', __('profile.tfa_enabled'));
        redirect('/profile/two-factor');
    }

    public function disableTwoFactor(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $password = (string) ($_POST['current_password'] ?? '');
        if (!password_verify($password, (string) $user['password_hash'])) {
            Session::flash('error', __('profile.password_wrong_current'));
            redirect('/profile/two-factor');
        }
        User::setTwoFactorSecret((int) $user['id'], null);
        unset($_SESSION['pending_2fa_secret']);
        ActivityLog::record('profile.tfa_disabled');
        Session::flash('success', __('profile.tfa_disabled'));
        redirect('/profile/two-factor');
    }

    /* ── Notifications (unchanged) ───────────────────────────────────── */

    public function notifications(): void
    {
        $user = Auth::user();
        $prefs = json_decode((string) ($user['notification_preferences'] ?? ''), true);
        echo View::render('profile/notifications', [
            'types'  => Notification::TYPES,
            'prefs'  => is_array($prefs) ? $prefs : [],
            'active' => 'notifications',
        ]);
    }

    public function saveNotifications(): void
    {
        $prefs = [];
        $posted = $_POST['pref'] ?? [];
        foreach (Notification::TYPES as $type) {
            $prefs[$type] = [
                'in_app' => !empty($posted[$type]['in_app']),
                'email'  => !empty($posted[$type]['email']),
            ];
        }
        User::updateNotificationPreferences((int) Auth::id(), $prefs);
        Session::flash('success', __('profile.saved'));
        redirect('/profile/notifications');
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    private static function passwordMeetsPolicy(string $pw, int $minLen): bool
    {
        return strlen($pw) >= $minLen
            && preg_match('/[A-Z]/', $pw)
            && preg_match('/[a-z]/', $pw)
            && preg_match('/\d/', $pw)
            && preg_match('/[^A-Za-z0-9]/', $pw);
    }
}
