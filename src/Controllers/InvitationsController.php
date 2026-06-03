<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Invitation;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewUser;
use SysRevAI\Models\User;

/**
 * Accept a review invitation via its token. Reachable by both logged-in
 * users (one-click join) and guests — when the invitee doesn't have a
 * SysRevAI account yet we render an inline sign-up form on the same
 * page; when they do we render a sign-in form. Both finish by adding
 * them to the review the token points at.
 */
final class InvitationsController
{
    public function show(string $token): void
    {
        $invitation = Invitation::findByToken($token);
        if ($invitation === null || !Invitation::isValid($invitation)) {
            Session::flash('login_error', __('invite.invalid'));
            redirect('/login');
        }
        $review = Review::find((int) $invitation['review_id']);

        // Email is normalised at create() so a case-insensitive match is
        // fine. We use it only to pick "sign in" vs. "sign up" — the
        // accept() handler re-validates everything regardless.
        $existing = User::findByEmail((string) $invitation['email']);
        $mode = Auth::check() ? 'authed' : ($existing !== null ? 'login' : 'register');

        echo View::render('invitations/accept', [
            'invitation' => $invitation,
            'review'     => $review,
            'mode'       => $mode,
            'minLen'     => max(8, (int) (setting('security.min_password_length') ?? 12)),
        ], Auth::check() ? null : 'layouts/auth');
    }

    public function accept(string $token): void
    {
        $invitation = Invitation::findByToken($token);
        if ($invitation === null || !Invitation::isValid($invitation)) {
            Session::flash('login_error', __('invite.invalid'));
            redirect('/login');
        }

        $reviewId = (int) $invitation['review_id'];

        // Authenticated path — the original one-click flow.
        if (Auth::check()) {
            ReviewUser::add($reviewId, (int) Auth::id(), (string) $invitation['role']);
            Invitation::markAccepted((int) $invitation['id']);
            ActivityLog::record('review.invitation_accepted', ['review_id' => $reviewId], $reviewId);
            Session::flash('success', __('invite.accepted'));
            redirect('/reviews/' . $reviewId);
        }

        // Guest path — either log in (account exists) or register
        // (it doesn't). The form posts back to the same URL with
        // `flow` set to one or the other.
        $flow  = (string) ($_POST['flow'] ?? '');
        $email = (string) $invitation['email'];
        $pw    = (string) ($_POST['password'] ?? '');

        $back = static function (string $msg) use ($token): void {
            Session::flash('invite_error', $msg);
            redirect('/invite/' . $token);
        };

        if ($flow === 'login') {
            if (!Auth::attempt($email, $pw)) {
                $back(__('auth.invalid_credentials'));
            }
            User::touchLogin((int) Auth::id());
            ReviewUser::add($reviewId, (int) Auth::id(), (string) $invitation['role']);
            Invitation::markAccepted((int) $invitation['id']);
            ActivityLog::record('review.invitation_accepted', ['review_id' => $reviewId], $reviewId);
            Session::flash('success', __('invite.accepted'));
            redirect('/reviews/' . $reviewId);
        }

        // 'register' (default for unauthenticated POST).
        if (User::findByEmail($email) !== null) {
            $back(__('auth.register_email_taken'));
        }
        $name   = trim((string) ($_POST['name'] ?? ''));
        $pw2    = (string) ($_POST['confirm'] ?? '');
        $minLen = max(8, (int) (setting('security.min_password_length') ?? 12));

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

        $id = User::create([
            'name'           => $name,
            'email'          => $email,
            'password_hash'  => password_hash($pw, PASSWORD_ARGON2ID),
            'role'           => 'reviewer',
            'status'         => 'active',
            'is_active'      => 1,
            'locale'         => (string) (setting('app.locale') ?? 'ca'),
            'legal_accepted' => true,
        ]);
        $user = User::find($id);
        if ($user !== null) {
            Auth::login($user);
            User::touchLogin($id);
        }
        ReviewUser::add($reviewId, $id, (string) $invitation['role']);
        Invitation::markAccepted((int) $invitation['id']);
        ActivityLog::record('review.invitation_accepted', ['review_id' => $reviewId, 'new_user' => true], $reviewId);
        Session::flash('success', __('invite.accepted'));
        redirect('/reviews/' . $reviewId);
    }
}
