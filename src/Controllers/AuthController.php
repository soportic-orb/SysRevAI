<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Crypto;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\User;
use SysRevAI\Services\Totp;

final class AuthController
{
    private const PENDING_KEY = '_auth_pending_2fa_user_id';

    public function showLogin(): void
    {
        echo View::render('auth/login', [
            'error' => Session::pullFlash('login_error'),
            'email' => Session::pullFlash('login_email', ''),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Session::flash('login_error', __('auth.invalid_credentials'));
            Session::flash('login_email', $email);
            redirect('/login');
        }

        // First leg: verify password without yet calling Auth::login(), so
        // the session is not yet marked as authenticated.
        if (!self::verifyPassword($email, $password)) {
            Session::flash('login_error', __('auth.invalid_credentials'));
            Session::flash('login_email', $email);
            redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!empty($user['two_factor_secret'])) {
            // Stash the user id and ask for a TOTP code. The session is
            // NOT marked as authenticated until the code is verified.
            $_SESSION[self::PENDING_KEY] = (int) $user['id'];
            redirect('/login/2fa');
        }

        Auth::login($user);
        User::touchLogin((int) $user['id']);
        redirect('/dashboard');
    }

    public function show2fa(): void
    {
        if (!isset($_SESSION[self::PENDING_KEY])) {
            redirect('/login');
        }
        echo View::render('auth/two_factor', [
            'error' => Session::pullFlash('login_error'),
        ], 'layouts/auth');
    }

    public function verify2fa(): void
    {
        $pendingId = (int) ($_SESSION[self::PENDING_KEY] ?? 0);
        if ($pendingId <= 0) {
            redirect('/login');
        }
        $user = User::find($pendingId);
        if ($user === null || empty($user['two_factor_secret'])) {
            unset($_SESSION[self::PENDING_KEY]);
            redirect('/login');
        }

        $secret = Crypto::decrypt((string) $user['two_factor_secret']);
        $code   = (string) ($_POST['code'] ?? '');

        if ($secret === null || !Totp::verify($secret, $code)) {
            Session::flash('login_error', __('auth.tfa_bad_code'));
            redirect('/login/2fa');
        }

        unset($_SESSION[self::PENDING_KEY]);
        Auth::login($user);
        User::touchLogin((int) $user['id']);
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    /**
     * Mirrors Auth::attempt() but does NOT establish the session.
     * Used by the two-step login so the 2FA prompt sits between the
     * password check and the session being marked authenticated.
     */
    private static function verifyPassword(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null) {
            // Equalise timing.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$' . str_repeat('a', 22) . '$' . str_repeat('a', 43));
            return false;
        }
        if (($user['status'] ?? 'active') !== 'active' || (int) ($user['is_active'] ?? 1) !== 1) {
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            User::registerFailedAttempt((int) $user['id']);
            return false;
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_ARGON2ID));
        }
        return true;
    }
}
