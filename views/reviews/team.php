<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $members */
/** @var array $invitations */
/** @var string[] $roles */
$id = (int) $review['id'];
$ownerId = (int) $review['owner_id'];
$inviteLink = Session::get('_last_invite_link');
Session::forget('_last_invite_link');
?>
<div class="page page--narrow">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('team.title')) ?></h1>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($inviteLink): ?>
        <div class="alert alert--success">
            <?= e(__('team.invite_link')) ?>:<br>
            <code class="invite-link"><?= e((string) $inviteLink) ?></code>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('team.invite')) ?></h2>
        <form method="post" action="/reviews/<?= $id ?>/team/invite" class="section-card--inline" style="padding:0">
            <?= csrf_field() ?>
            <input class="input" name="email" type="email" placeholder="colleague@example.com" required>
            <select class="select select--sm" name="role">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>"><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--primary"><?= e(__('team.send_invite')) ?></button>
        </form>
        <p class="field-help"><?= e(__('team.invite_help')) ?></p>
    </div>

    <div class="section-card">
        <h2 class="section__subtitle"><?= e(__('team.members')) ?></h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(__('team.member')) ?></th><th><?= e(__('team.options')) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($members as $m): $isOwnerRow = (int) $m['id'] === $ownerId; ?>
                        <tr>
                            <td><strong><?= e((string) $m['name']) ?></strong><br><span class="muted"><?= e((string) $m['email']) ?></span></td>
                            <td>
                                <?php if ($isOwnerRow): ?>
                                    <span class="tag tag--soft">owner</span>
                                <?php else: ?>
                                    <form method="post" action="/reviews/<?= $id ?>/team/update" class="row-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                        <select class="select select--sm" name="role">
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= $r ?>" <?= $m['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="checkbox"><input type="checkbox" name="is_blinded" value="1" <?= (int) $m['is_blinded'] === 1 ? 'checked' : '' ?>> <?= e(__('team.blinded')) ?></label>
                                        <label class="checkbox"><input type="checkbox" name="can_resolve_conflicts" value="1" <?= (int) $m['can_resolve_conflicts'] === 1 ? 'checked' : '' ?>> <?= e(__('team.can_resolve')) ?></label>
                                        <button class="btn btn--ghost btn--sm"><?= e(__('admin.save')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$isOwnerRow): ?>
                                    <form method="post" action="/reviews/<?= $id ?>/team/remove" onsubmit="return confirm('?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                        <button class="btn btn--danger btn--sm"><?= e(__('team.remove')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($invitations !== []): ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('team.pending')) ?></h2>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <?php foreach ($invitations as $inv): ?>
                            <tr>
                                <td><?= e((string) $inv['email']) ?> <span class="tag tag--soft"><?= e((string) $inv['role']) ?></span></td>
                                <td class="muted"><?= e(__('team.expires')) ?>: <?= e((string) $inv['expires_at']) ?></td>
                                <td>
                                    <form method="post" action="/reviews/<?= $id ?>/team/revoke">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                                        <button class="btn btn--ghost btn--sm"><?= e(__('team.revoke')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
