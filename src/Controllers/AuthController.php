<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Crypto;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Invitation;
use SysRevAI\Models\ReviewUser;
use SysRevAI\Models\User;
use SysRevAI\Models\UserInvitation;
use SysRevAI\Services\MailService;
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

    /* ── Self-registration (toggled in Admin → Users) ────────────────── */

    public function showRegister(): void
    {
        if (!self::registrationOpen()) {
            redirect('/login');
        }
        echo View::render('auth/register', [
            'error'         => Session::pullFlash('register_error'),
            'old'           => Session::pullFlash('register_old', ['name' => '', 'email' => '']),
            'minLen'        => max(8, (int) (setting('security.min_password_length') ?? 12)),
            'emailDomain'   => trim((string) (setting('registration.email_domain') ?? '')),
            'manualApprove' => (bool) (setting('registration.manual_approval') ?? true),
        ], 'layouts/auth');
    }

    public function register(): void
    {
        if (!self::registrationOpen()) {
            redirect('/login');
        }

        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $pw    = (string) ($_POST['password'] ?? '');
        $pw2   = (string) ($_POST['confirm'] ?? '');
        $acceptPrivacy = !empty($_POST['accept_privacy']);
        $acceptTerms   = !empty($_POST['accept_terms']);

        $back = static function (string $msg) use ($name, $email): void {
            Session::flash('register_error', $msg);
            Session::flash('register_old', ['name' => $name, 'email' => $email]);
            redirect('/register');
        };

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $back(__('auth.register_invalid'));
        }
        if (!$acceptPrivacy || !$acceptTerms) {
            $back(__('auth.register_must_accept_legal'));
        }
        if ($pw !== $pw2) {
            $back(__('auth.register_pw_mismatch'));
        }

        $minLen = max(8, (int) (setting('security.min_password_length') ?? 12));
        if (!self::passwordMeetsPolicy($pw, $minLen)) {
            $back(__('auth.register_pw_weak', $minLen));
        }

        $domain = trim((string) (setting('registration.email_domain') ?? ''));
        if ($domain !== '') {
            $needle = strtolower(ltrim($domain, '@'));
            if (!str_ends_with($email, '@' . $needle)) {
                $back(__('auth.register_bad_domain', $domain));
            }
        }
        if (User::findByEmail($email) !== null) {
            $back(__('auth.register_email_taken'));
        }

        // An admin-issued invitation (platform-level or review-scoped) for
        // this email is a trust signal: skip the manual-approval gate and,
        // if there's a platform invitation, honour the role the admin picked.
        $userInvitation   = UserInvitation::findValidForEmail($email);
        $reviewInvitations = Invitation::pendingForEmail($email);
        $hasInvitation     = $userInvitation !== null || $reviewInvitations !== [];

        $manualApprove = (bool) (setting('registration.manual_approval') ?? true);
        $autoApprove   = $hasInvitation || !$manualApprove;
        $status        = $autoApprove ? 'active' : 'pending';
        $isActive      = $autoApprove ? 1 : 0;
        $role          = $userInvitation !== null ? (string) $userInvitation['role'] : 'reviewer';

        $id = User::create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($pw, PASSWORD_ARGON2ID),
            'role'          => $role,
            'status'        => $status,
            'locale'        => (string) (setting('app.locale') ?? 'ca'),
            'is_active'     => $isActive,
            'legal_accepted' => true,
        ]);
        ActivityLog::record('users.self_registered', [
            'user_id'         => $id,
            'manual_approval' => $manualApprove,
            'invited'         => $hasInvitation,
        ]);

        if ($userInvitation !== null) {
            UserInvitation::markAccepted((int) $userInvitation['id']);
            ActivityLog::record('users.invitation_accepted', [
                'user_id' => $id,
                'role'    => $userInvitation['role'],
            ]);
        }

        $joinedReviewIds = [];
        foreach ($reviewInvitations as $inv) {
            $reviewId = (int) $inv['review_id'];
            ReviewUser::add($reviewId, $id, (string) $inv['role']);
            Invitation::markAccepted((int) $inv['id']);
            $joinedReviewIds[] = $reviewId;
        }
        if ($joinedReviewIds !== []) {
            ActivityLog::record('reviews.invitation_accepted_on_register', [
                'user_id'    => $id,
                'review_ids' => $joinedReviewIds,
            ]);
        }

        // Notify the platform's owners / admins so they don't have to
        // poll the admin panel for new sign-ups. Wrapped in a try/catch
        // because mail must never break the primary registration flow.
        try {
            $siteName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
            $siteUrl  = (string) (setting('site.url')  ?? config('app.url', ''));
            $whenLine = 'Signed up at ' . date('Y-m-d H:i T');

            if (!$autoApprove) {
                $subject = sprintf('[%s] New user awaiting validation: %s', $siteName, $name);
                $body = "A new user has signed up and is awaiting administrator validation.\n\n"
                    . "Name:  {$name}\n"
                    . "Email: {$email}\n"
                    . "{$whenLine}\n\n"
                    . ($siteUrl !== '' ? "Approve them in the admin panel: {$siteUrl}/admin/users\n" : '');
            } else {
                $reviewLine = $joinedReviewIds !== []
                    ? 'Auto-added to ' . count($joinedReviewIds) . ' invited review(s).' . "\n"
                    : '';
                $invitedLine = $hasInvitation
                    ? "Activated via a pending invitation; admin approval was skipped.\n"
                    : '';
                $subject = sprintf('[%s] New user registered: %s', $siteName, $name);
                $body = "A new user has signed up and is already active.\n\n"
                    . "Name:  {$name}\n"
                    . "Email: {$email}\n"
                    . "{$whenLine}\n"
                    . $invitedLine
                    . $reviewLine
                    . "\n"
                    . ($siteUrl !== '' ? "Manage users: {$siteUrl}/admin/users\n" : '');
            }
            MailService::notifyAdmins($subject, $body);
        } catch (\Throwable) {
            // Best-effort: ignore mail failures.
        }

        if (!$autoApprove) {
            Session::flash('login_email', $email);
            Session::flash('login_error', __('auth.register_pending'));
            redirect('/login');
        }

        // Auto-approved: log the user straight in.
        $user = User::find($id);
        if ($user !== null) {
            Auth::login($user);
            User::touchLogin($id);
        }
        redirect('/dashboard');
    }

    public static function registrationOpen(): bool
    {
        return (bool) (setting('registration.open') ?? false);
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

    private static function passwordMeetsPolicy(string $pw, int $minLen): bool
    {
        return strlen($pw) >= $minLen
            && preg_match('/[A-Z]/', $pw)
            && preg_match('/[a-z]/', $pw)
            && preg_match('/\d/', $pw)
            && preg_match('/[^A-Za-z0-9]/', $pw);
    }
}
