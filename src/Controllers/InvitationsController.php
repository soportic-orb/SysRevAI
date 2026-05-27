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

/**
 * Accept a review invitation via its token (login required).
 */
final class InvitationsController
{
    public function show(string $token): void
    {
        $invitation = Invitation::findByToken($token);
        if ($invitation === null || !Invitation::isValid($invitation)) {
            Session::flash('error', __('invite.invalid'));
            redirect('/dashboard');
        }
        $review = Review::find((int) $invitation['review_id']);
        echo View::render('invitations/accept', [
            'invitation' => $invitation,
            'review'     => $review,
        ], 'layouts/auth');
    }

    public function accept(string $token): void
    {
        $invitation = Invitation::findByToken($token);
        if ($invitation === null || !Invitation::isValid($invitation)) {
            Session::flash('error', __('invite.invalid'));
            redirect('/dashboard');
        }

        $reviewId = (int) $invitation['review_id'];
        ReviewUser::add($reviewId, (int) Auth::id(), (string) $invitation['role']);
        Invitation::markAccepted((int) $invitation['id']);
        ActivityLog::record('review.invitation_accepted', ['review_id' => $reviewId], $reviewId);
        Session::flash('success', __('invite.accepted'));
        redirect('/reviews/' . $reviewId);
    }
}
