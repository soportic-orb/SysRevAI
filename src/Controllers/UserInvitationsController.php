<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\User;
use SysRevAI\Models\UserInvitation;

/**
 * Platform-level invitation acceptance. The admin issues a token from
 * /admin/users; the invitee opens /user-invite/{token}, sets their name
 * and password, and we create the account with the role the admin
 * pre-selected. Guests-only — already-logged-in users get redirected
 * to the dashboard because the invite would create a new account on
 * top of their session.
 */
final class UserInvitationsController
{
    public function show(string $token): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        $invitation = UserInvitation::findByToken($token);
        if ($invitation === null || !UserInvitation::isValid($invitation)) {
            Session::flash('login_error', __('invite.invalid'));
            redirect('/login');
        }
        echo View::render('user_invitations/accept', [
            'invitation' => $invitation,
            'minLen'     => max(8, (int) (setting('security.min_password_length') ?? 12)),
        ], 'layouts/auth');
    }

    public function accept(string $token): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        $invitation = UserInvitation::findByToken($token);
        if ($invitation === null || !UserInvitation::isValid($invitation)) {
            Session::flash('login_error', __('invite.invalid'));
            redirect('/login');
        }

        $email  = (string) $invitation['email'];
        $name   = trim((string) ($_POST['name'] ?? ''));
        $pw     = (string) ($_POST['password'] ?? '');
        $pw2    = (string) ($_POST['confirm']  ?? '');
        $minLen = max(8, (int) (setting('security.min_password_length') ?? 12));

        $back = static function (string $msg) use ($token, $name): void {
            Session::flash('user_invite_error', $msg);
            Session::flash('user_invite_old', ['name' => $name]);
            redirect('/user-invite/' . $token);
        };

        if ($name === '') {
            $back(__('invite.user_name_required'));
        }
        if ($pw !== $pw2) {
            $back(__('auth.register_pw_mismatch'));
        }
        if (strlen($pw) < $minLen
            || !preg_match('/[a-z]/', $pw)
            || !preg_match('/[A-Z]/', $pw)
            || !preg_match('/\d/', $pw)
            || !preg_match('/[^A-Za-z0-9]/', $pw)) {
            $back(__('auth.register_pw_weak', $minLen));
        }
        if (User::findByEmail($email) !== null) {
            // Someone else registered with the invited email between the
            // admin issuing the token and the invitee accepting it.
            Session::flash('login_email', $email);
            Session::flash('login_error', __('auth.register_email_taken'));
            redirect('/login');
        }

        $id = User::create([
            'name'           => $name,
            'email'          => $email,
            'password_hash'  => password_hash($pw, PASSWORD_ARGON2ID),
            'role'           => (string) $invitation['role'],
            'status'         => 'active',
            'is_active'      => 1,
            'locale'         => (string) (setting('app.locale') ?? 'ca'),
            'legal_accepted' => true,
        ]);
        UserInvitation::markAccepted((int) $invitation['id']);
        ActivityLog::record('users.invitation_accepted', ['user_id' => $id, 'role' => $invitation['role']]);

        $user = User::find($id);
        if ($user !== null) {
            Auth::login($user);
            User::touchLogin($id);
        }
        redirect('/dashboard');
    }
}
