<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Invitation;
use SysRevAI\Models\Notification;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewUser;
use SysRevAI\Models\User;

/**
 * Manage a review's collaborators and invitations (review owner only).
 */
final class MembersController
{
    private const ROLES = ['admin', 'reviewer', 'viewer'];

    public function index(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        echo View::render('reviews/team', [
            'review'      => $review,
            'members'     => ReviewUser::forReview((int) $id),
            'invitations' => Invitation::forReview((int) $id),
            'roles'       => self::ROLES,
        ]);
    }

    public function invite(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $rid = (int) $id;
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role  = in_array(($_POST['role'] ?? 'reviewer'), self::ROLES, true) ? $_POST['role'] : 'reviewer';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('team.email_invalid'));
            redirect('/reviews/' . $rid . '/team');
        }

        $existing = User::findByEmail($email);
        if ($existing !== null) {
            ReviewUser::add($rid, (int) $existing['id'], $role);
            Notification::push(
                (int) $existing['id'],
                'invitation',
                __('team.notif_added_title', $review['title']),
                __('team.notif_added_msg', $review['title']),
                '/reviews/' . $rid,
                $rid
            );
            ActivityLog::record('review.member_added', ['user_id' => (int) $existing['id'], 'role' => $role], $rid);
            Session::flash('success', __('team.member_added'));
        } else {
            $token = Invitation::create($rid, $email, $role, (int) Auth::id());
            Session::set('_last_invite_link', base_url('/invite/' . $token));
            ActivityLog::record('review.invited', ['email' => $email, 'role' => $role], $rid);
            Session::flash('success', __('team.invitation_created'));
        }
        redirect('/reviews/' . $rid . '/team');
    }

    public function updateMember(string $id): void
    {
        $this->ownerOrDeny((int) $id);
        $rid = (int) $id;
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = in_array(($_POST['role'] ?? 'reviewer'), ['owner', ...self::ROLES], true) ? $_POST['role'] : 'reviewer';
        $blinded = !empty($_POST['is_blinded']);
        $resolve = !empty($_POST['can_resolve_conflicts']);

        if ($userId !== (int) Auth::id()) { // owner keeps their own role
            ReviewUser::updateMembership($rid, $userId, $role, $blinded, $resolve);
            ActivityLog::record('review.member_updated', ['user_id' => $userId, 'role' => $role], $rid);
        }
        Session::flash('success', __('admin.saved'));
        redirect('/reviews/' . $rid . '/team');
    }

    public function removeMember(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $rid = (int) $id;
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId === (int) $review['owner_id']) {
            Session::flash('error', __('team.cant_remove_owner'));
            redirect('/reviews/' . $rid . '/team');
        }
        ReviewUser::remove($rid, $userId);
        ActivityLog::record('review.member_removed', ['user_id' => $userId], $rid);
        Session::flash('success', __('team.member_removed'));
        redirect('/reviews/' . $rid . '/team');
    }

    public function revokeInvitation(string $id): void
    {
        $this->ownerOrDeny((int) $id);
        $rid = (int) $id;
        Invitation::revoke((int) ($_POST['invitation_id'] ?? 0), $rid);
        Session::flash('success', __('team.invitation_revoked'));
        redirect('/reviews/' . $rid . '/team');
    }

    private function ownerOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || (int) $review['owner_id'] !== (int) Auth::id()) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
