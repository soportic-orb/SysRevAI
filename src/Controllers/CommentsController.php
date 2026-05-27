<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Models\Comment;
use SysRevAI\Models\Notification;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewUser;

/**
 * Review discussion board comments, with @mentions.
 */
final class CommentsController
{
    public function store(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            redirect('/reviews/' . $rid . '#comments');
        }

        $members = ReviewUser::forReview($rid);
        [$mentionIds, $mentionNames] = Comment::resolveMentions($content, $members);
        Comment::create($rid, (int) Auth::id(), $content, null, null, $mentionNames);

        $authorId = (int) Auth::id();
        $authorName = (string) (Auth::user()['name'] ?? '');

        foreach ($members as $member) {
            $memberId = (int) $member['id'];
            if ($memberId === $authorId) {
                continue;
            }
            if (in_array($memberId, $mentionIds, true)) {
                Notification::push($memberId, 'mention', __('comments.notif_mention', $authorName, $review['title']), $content, '/reviews/' . $rid . '#comments', $rid);
            } else {
                Notification::push($memberId, 'comment', __('comments.notif_new', $authorName, $review['title']), $content, '/reviews/' . $rid . '#comments', $rid);
            }
        }

        Session::flash('success', __('comments.posted'));
        redirect('/reviews/' . $rid . '#comments');
    }

    public function delete(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $comment = Comment::find($commentId);

        if ($comment !== null && (int) $comment['review_id'] === $rid
            && ((int) $comment['user_id'] === (int) Auth::id() || (int) $review['owner_id'] === (int) Auth::id())) {
            Comment::softDelete($commentId);
            Session::flash('success', __('comments.deleted'));
        }
        redirect('/reviews/' . $rid . '#comments');
    }

    private function memberOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo \SysRevAI\Core\View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
