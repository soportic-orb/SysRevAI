<?php

declare(strict_types=1);

/** @var array $users */
/** @var array $invitations */
/** @var ?string $lastInviteLink */
/** @var string $search */
/** @var string[] $roles */
$regOpen   = (bool) (setting('registration.open') ?? false);
$regManual = (bool) (setting('registration.manual_approval') ?? true);
$regDomain = (string) (setting('registration.email_domain') ?? '');
$statuses  = ['active', 'pending', 'suspended'];
$minLen    = (int) (setting('security.min_password_length') ?? 12);
?>
<h1 class="section__title"><?= e(__('admin.sections.users')) ?></h1>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.users.registration')) ?></h2>
    <form method="post" action="/admin/users/registration" class="form-grid">
        <?= csrf_field() ?>
        <label class="checkbox">
            <input type="checkbox" name="open" value="1" <?= $regOpen ? 'checked' : '' ?>>
            <?= e(__('admin.users.reg_open')) ?>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="manual_approval" value="1" <?= $regManual ? 'checked' : '' ?>>
            <?= e(__('admin.users.reg_manual')) ?>
        </label>
        <div class="field">
            <label class="field-label" for="email_domain"><?= e(__('admin.users.reg_domain')) ?></label>
            <input class="input" id="email_domain" name="email_domain" value="<?= e($regDomain) ?>" placeholder="hospital.cat">
        </div>
        <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
    </form>
</div>

<?php if ($lastInviteLink): ?>
    <div class="alert alert--success invite-link-banner">
        <strong><?= e(__('admin.users.invite_link_label')) ?>:</strong>
        <code class="invite-link"><?= e((string) $lastInviteLink) ?></code>
        <button type="button"
                class="btn btn--ghost btn--sm copy-to-clipboard"
                data-copy="<?= e((string) $lastInviteLink) ?>"
                data-copy-ok="<?= e(__('team.invite_link_copied')) ?>">
            <?= e(__('team.invite_link_copy')) ?>
        </button>
    </div>
<?php endif; ?>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.users.invite_by_link')) ?></h2>
    <p class="section__intro"><?= e(__('admin.users.invite_by_link_help')) ?></p>
    <form method="post" action="/admin/users/invite" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="invite_email"><?= e(__('admin.users.email')) ?></label>
                <input class="input" id="invite_email" name="email" type="email" required>
            </div>
            <div class="field">
                <label class="field-label" for="invite_role"><?= e(__('admin.users.role')) ?></label>
                <select class="select" id="invite_role" name="role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>" <?= $r === 'reviewer' ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div><button type="submit" class="btn btn--primary"><?= e(__('admin.users.invite_btn')) ?></button></div>
    </form>
</div>

<?php if ($invitations !== []): ?>
<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.users.invitations_pending')) ?></h2>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <?php foreach ($invitations as $inv):
                    $url = \SysRevAI\Models\UserInvitation::inviteUrl((string) $inv['token']);
                ?>
                    <tr>
                        <td>
                            <?= e((string) $inv['email']) ?>
                            <span class="tag tag--soft"><?= e((string) $inv['role']) ?></span><br>
                            <code class="invite-link invite-link--inline"><?= e($url) ?></code>
                        </td>
                        <td class="muted"><?= e(__('team.expires')) ?>: <?= e((string) $inv['expires_at']) ?></td>
                        <td class="invite-row__actions">
                            <button type="button"
                                    class="btn btn--ghost btn--sm copy-to-clipboard"
                                    data-copy="<?= e($url) ?>"
                                    data-copy-ok="<?= e(__('team.invite_link_copied')) ?>">
                                <?= e(__('team.invite_link_copy')) ?>
                            </button>
                            <form method="post" action="/admin/users/invitations/<?= (int) $inv['id'] ?>/revoke" style="display:inline">
                                <?= csrf_field() ?>
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

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.users.new_user')) ?></h2>
    <form method="post" action="/admin/users" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="name"><?= e(__('admin.users.name')) ?></label>
                <input class="input" id="name" name="name" required>
            </div>
            <div class="field">
                <label class="field-label" for="email"><?= e(__('admin.users.email')) ?></label>
                <input class="input" id="email" name="email" type="email" required>
            </div>
        </div>
        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="password"><?= e(__('admin.users.password')) ?></label>
                <input class="input" id="password" name="password" type="password" minlength="<?= $minLen ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="role"><?= e(__('admin.users.role')) ?></label>
                <select class="select" id="role" name="role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>" <?= $r === 'reviewer' ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div><button type="submit" class="btn btn--primary"><?= e(__('admin.users.create')) ?></button></div>
    </form>
</div>

<div class="section-card">
    <form method="get" action="/admin/users" class="section-card--inline" style="padding:0;margin-bottom:14px">
        <input class="input" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('admin.users.search')) ?>">
        <button type="submit" class="btn btn--ghost"><?= e(__('admin.users.search')) ?></button>
    </form>

    <?php if ($users === []): ?>
        <p class="section__intro"><?= e(__('admin.users.none')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('admin.users.name')) ?></th>
                    <th><?= e(__('admin.users.role')) ?></th>
                    <th><?= e(__('admin.users.status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $u['name']) ?></strong><br>
                            <span class="muted"><?= e((string) $u['email']) ?></span>
                        </td>
                        <td colspan="2">
                            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>" class="row-form">
                                <?= csrf_field() ?>
                                <select class="select select--sm" name="role">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="select select--sm" name="status">
                                    <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $u['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.users.st_' . $s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="checkbox">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) $u['is_active'] === 1 ? 'checked' : '' ?>>
                                    <?= e(__('admin.users.active')) ?>
                                </label>
                                <button type="submit" class="btn btn--ghost btn--sm"><?= e(__('admin.save')) ?></button>
                            </form>
                        </td>
                        <td>
                            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/delete"
                                  onsubmit="return confirm('<?= e(__('admin.users.confirm_delete')) ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--danger btn--sm"><?= e(__('admin.users.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
