<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Config;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\User;
use SysRevAI\Models\UserInvitation;

/**
 * Admin → Users and permissions: list, create, update and delete accounts,
 * plus registration policy settings.
 */
final class UsersController
{
    private const ROLES = ['owner', 'admin', 'reviewer', 'viewer'];

    public function index(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        try {
            $users = User::all($search);
        } catch (\Throwable) {
            $users = [];
        }
        // Pending platform-level invitations. Wrapped in try/catch
        // because the user_invitations table only exists after migration
        // 023; older installs shouldn't blank the page.
        try {
            $invitations = UserInvitation::pending();
        } catch (\Throwable) {
            $invitations = [];
        }
        // Surface the last-created invite link as a one-shot banner so
        // the admin can copy it right away — important when SMTP is
        // misconfigured or the invitation email never arrives.
        $lastInviteLink = Session::get('_last_user_invite_link');
        Session::forget('_last_user_invite_link');

        echo View::render('admin/users/index', [
            'activeSection'  => 'users',
            'users'          => $users,
            'invitations'    => $invitations,
            'lastInviteLink' => $lastInviteLink,
            'search'         => $search,
            'roles'          => self::ROLES,
        ], 'layouts/admin');
    }

    /**
     * Admin-issued invitation. Creates a token-backed user_invitation
     * row and stashes the resulting URL so the admin can copy it from
     * the page — useful when the SMTP layer is down or the invitee
     * never receives the email.
     */
    public function invite(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role  = in_array(($_POST['role'] ?? 'reviewer'), self::ROLES, true) ? $_POST['role'] : 'reviewer';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('admin_error', __('admin.users.invite_invalid_email'));
            redirect('/admin/users');
        }
        if (User::findByEmail($email) !== null) {
            Session::flash('admin_error', __('admin.users.email_taken'));
            redirect('/admin/users');
        }

        $token = UserInvitation::create($email, $role, (int) Auth::id());
        Session::set('_last_user_invite_link', UserInvitation::inviteUrl($token));
        ActivityLog::record('users.invited', ['email' => $email, 'role' => $role]);
        Session::flash('admin_success', __('admin.users.invitation_created'));
        redirect('/admin/users');
    }

    public function revokeInvitation(string $id): void
    {
        UserInvitation::revoke((int) $id);
        ActivityLog::record('users.invitation_revoked', ['invitation_id' => (int) $id]);
        Session::flash('admin_success', __('admin.users.invitation_revoked'));
        redirect('/admin/users');
    }

    public function create(): void
    {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $pw    = (string) ($_POST['password'] ?? '');
        $role  = in_array(($_POST['role'] ?? 'reviewer'), self::ROLES, true) ? $_POST['role'] : 'reviewer';

        $minLen = (int) (setting('security.min_password_length') ?? 12);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < $minLen) {
            Session::flash('admin_error', __('admin.users.create_invalid'));
            redirect('/admin/users');
        }
        if (User::findByEmail($email) !== null) {
            Session::flash('admin_error', __('admin.users.email_taken'));
            redirect('/admin/users');
        }

        $id = User::create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($pw, PASSWORD_ARGON2ID),
            'role'          => $role,
            'status'        => 'active',
            'locale'        => (string) (setting('app.locale') ?? 'ca'),
        ]);
        ActivityLog::record('users.created', ['user_id' => $id, 'role' => $role]);
        Session::flash('admin_success', __('admin.users.created'));
        redirect('/admin/users');
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        $role   = in_array(($_POST['role'] ?? 'reviewer'), self::ROLES, true) ? $_POST['role'] : 'reviewer';
        $status = in_array(($_POST['status'] ?? 'active'), ['active', 'pending', 'suspended'], true) ? $_POST['status'] : 'active';
        $active = !empty($_POST['is_active']);

        // Guard: never strip the last remaining owner.
        $target = User::find($id);
        if ($target !== null && $target['role'] === 'owner' && $role !== 'owner' && User::countOwners() <= 1) {
            Session::flash('admin_error', __('admin.users.last_owner'));
            redirect('/admin/users');
        }

        User::updateAccount($id, $role, $status, $active);
        ActivityLog::record('users.updated', ['user_id' => $id, 'role' => $role, 'status' => $status]);
        Session::flash('admin_success', __('admin.saved'));
        redirect('/admin/users');
    }

    public function delete(string $id): void
    {
        $id = (int) $id;
        if ($id === Auth::id()) {
            Session::flash('admin_error', __('admin.users.cant_delete_self'));
            redirect('/admin/users');
        }
        $target = User::find($id);
        if ($target !== null && $target['role'] === 'owner' && User::countOwners() <= 1) {
            Session::flash('admin_error', __('admin.users.last_owner'));
            redirect('/admin/users');
        }

        User::delete($id);
        ActivityLog::record('users.deleted', ['user_id' => $id]);
        Session::flash('admin_success', __('admin.users.deleted'));
        redirect('/admin/users');
    }

    public function saveRegistration(): void
    {
        Config::set('registration.open', !empty($_POST['open']), 'bool', 'users', false);
        Config::set('registration.manual_approval', !empty($_POST['manual_approval']), 'bool', 'users', false);
        Config::set('registration.email_domain', trim((string) ($_POST['email_domain'] ?? '')), 'string', 'users', false);
        ActivityLog::record('settings.registration.updated');
        Session::flash('admin_success', __('admin.saved'));
        redirect('/admin/users');
    }
}
