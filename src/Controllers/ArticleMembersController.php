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
use SysRevAI\Models\User;

/**
 * Article collaboration: members + invitations. Mirrors the review's
 * MembersController. Only the owner can invite, remove and revoke.
 */
final class ArticleMembersController
{
    public function index(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        echo View::render('articles/team', [
            'article'     => $article,
            'isOwner'     => Article::isOwner($article, (int) Auth::id()),
            'members'     => ArticleUser::forArticle((int) $id),
            'invitations' => ArticleInvitation::forArticle((int) $id),
        ]);
    }

    public function invite(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $rid = (int) $id;
        if (!Article::isOwner($article, (int) Auth::id())) {
            Session::flash('error', __('articles.team.owner_only'));
            redirect('/tools/articles/' . $rid . '/team');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('articles.team.bad_email'));
            redirect('/tools/articles/' . $rid . '/team');
        }

        $existing = User::findByEmail($email);
        if ($existing !== null) {
            ArticleUser::add($rid, (int) $existing['id']);
            ActivityLog::record('articles.team.member_added', ['article_id' => $rid, 'user_id' => (int) $existing['id']]);
            Session::flash('success', __('articles.team.added_existing'));
            redirect('/tools/articles/' . $rid . '/team');
        }

        $token = ArticleInvitation::create($rid, $email, 'collaborator', (int) Auth::id());
        ActivityLog::record('articles.team.invitation_created', ['article_id' => $rid, 'email' => $email]);
        Session::flash('success', __('articles.team.invitation_created'));
        redirect('/tools/articles/' . $rid . '/team');
    }

    public function removeMember(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $rid = (int) $id;
        if (!Article::isOwner($article, (int) Auth::id())) {
            Session::flash('error', __('articles.team.owner_only'));
            redirect('/tools/articles/' . $rid . '/team');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) $article['owner_id']) {
            ArticleUser::remove($rid, $userId);
            ActivityLog::record('articles.team.member_removed', ['article_id' => $rid, 'user_id' => $userId]);
            Session::flash('success', __('articles.team.member_removed'));
        }
        redirect('/tools/articles/' . $rid . '/team');
    }

    public function revokeInvitation(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $rid = (int) $id;
        if (!Article::isOwner($article, (int) Auth::id())) {
            Session::flash('error', __('articles.team.owner_only'));
            redirect('/tools/articles/' . $rid . '/team');
        }
        ArticleInvitation::revoke((int) ($_POST['invitation_id'] ?? 0), $rid);
        Session::flash('success', __('articles.team.invitation_revoked'));
        redirect('/tools/articles/' . $rid . '/team');
    }

    private function loadOrDeny(int $id): array
    {
        $article = Article::find($id);
        $uid = (int) Auth::id();
        if ($article === null || !Article::userCanAccess($id, $uid)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $article;
    }
}
