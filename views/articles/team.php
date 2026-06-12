<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Models\ArticleInvitation;

/** @var array $article */
/** @var bool  $isOwner */
/** @var array $members */
/** @var array $invitations */
$id = (int) $article['id'];
?>
<div class="page">
    <div class="page__head article-head">
        <div class="article-head__title">
            <h1 class="page__title">
                <?= e((string) ($article['title'] ?: '—')) ?>
                <span class="muted">— <?= e(__('articles.team_title')) ?></span>
            </h1>
        </div>
        <?php
            $articleActionsActive = 'team';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($isOwner): ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('articles.team.invite_title')) ?></h2>
            <p class="muted"><?= e(__('articles.team.invite_help')) ?></p>
            <form method="post" action="/tools/articles/<?= $id ?>/team/invite" class="form-grid">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="email"><?= e(__('articles.team.email')) ?></label>
                    <input class="input" id="email" name="email" type="email" required>
                </div>
                <div><button class="btn btn--primary" type="submit"><?= e(__('articles.team.invite_btn')) ?></button></div>
            </form>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('articles.team.members_title')) ?></h2>
        <ul class="member-list">
            <li>
                <strong><?= e((string) ($article['owner_id'] ?? '')) ?></strong>
                <span class="tag tag--soft"><?= e(__('articles.team.role_owner')) ?></span>
            </li>
            <?php foreach ($members as $m): ?>
                <li>
                    <strong><?= e((string) $m['name']) ?></strong> · <span class="muted"><?= e((string) $m['email']) ?></span>
                    <span class="tag tag--soft"><?= e($m['role']) ?></span>
                    <?php if ($isOwner): ?>
                        <form method="post" action="/tools/articles/<?= $id ?>/team/remove"
                              style="display:inline; margin-left:8px"
                              data-confirm="<?= e(__('articles.team.remove_confirm', (string) $m['name'])) ?>"
                              data-confirm-tone="danger"
                              data-confirm-button="<?= e(__('articles.team.remove_btn')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn--ghost btn--xs btn--danger" type="submit">
                                <?= e(__('articles.team.remove_btn')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($isOwner && $invitations !== []): ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('articles.team.pending_title')) ?></h2>
            <ul class="member-list">
                <?php foreach ($invitations as $inv): ?>
                    <li>
                        <strong><?= e((string) $inv['email']) ?></strong>
                        <span class="muted"><?= e((string) $inv['created_at']) ?></span>
                        <a class="muted" href="<?= e(ArticleInvitation::inviteUrl((string) $inv['token'])) ?>" target="_blank" rel="noopener noreferrer">
                            <?= e(__('articles.team.link')) ?>
                        </a>
                        <form method="post" action="/tools/articles/<?= $id ?>/team/revoke"
                              style="display:inline; margin-left:8px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                            <button class="btn btn--ghost btn--xs" type="submit">
                                <?= e(__('articles.team.revoke_btn')) ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
