<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleInvitation;
use SysRevAI\Models\ArticleUser;

/**
 * Invitee-side flow for article collaboration. Logged-in users accept
 * via POST; the unauth landing redirects them through login first.
 */
final class ArticleInvitationsController
{
    public function show(string $token): void
    {
        $invitation = ArticleInvitation::findByToken($token);
        if ($invitation === null || !ArticleInvitation::isValid($invitation)) {
            Session::flash('error', __('articles.team.invitation_invalid'));
            redirect('/');
        }
        if (!Auth::check()) {
            $_SESSION['_pending_article_invite'] = $token;
            redirect('/login');
        }
        $article = Article::find((int) $invitation['article_id']);
        echo View::render('articles/invite_accept', [
            'invitation' => $invitation,
            'article'    => $article,
        ], 'layouts/auth');
    }

    public function accept(string $token): void
    {
        $invitation = ArticleInvitation::findByToken($token);
        if ($invitation === null || !ArticleInvitation::isValid($invitation)) {
            Session::flash('error', __('articles.team.invitation_invalid'));
            redirect('/');
        }
        $uid = (int) Auth::id();
        $articleId = (int) $invitation['article_id'];
        ArticleUser::add($articleId, $uid, (string) ($invitation['role'] ?? 'collaborator'));
        ArticleInvitation::markAccepted((int) $invitation['id']);
        ActivityLog::record('articles.team.invitation_accepted', ['article_id' => $articleId]);
        Session::flash('success', __('articles.team.joined'));
        redirect('/tools/articles/' . $articleId);
    }
}
