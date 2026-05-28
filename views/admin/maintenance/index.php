<?php

declare(strict_types=1);

/** @var bool $maintenanceMode */
/** @var array $system */
/** @var array $backups */
/** @var array $activity */
/** @var array|null $updateInfo */
/** @var array $updateHistory */
/** @var string $updateRepo */
/** @var string $updateBranch */
?>
<h1 class="section__title"><?= e(__('admin.sections.maintenance')) ?></h1>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.updates')) ?></h2>
    <p class="section__intro">
        <?= e(__('admin.maintenance.updates_intro')) ?>
        <code><?= e($updateRepo) ?></code> · <code><?= e($updateBranch) ?></code>
    </p>

    <div class="btn-row" style="margin-bottom:12px">
        <form method="post" action="/admin/maintenance/update-check">
            <?= csrf_field() ?>
            <button class="btn btn--ghost"><?= e(__('admin.maintenance.check_updates')) ?></button>
        </form>
        <?php if (is_array($updateInfo) && ($updateInfo['ok'] ?? false) && !($updateInfo['up_to_date'] ?? true)): ?>
            <form method="post" action="/admin/maintenance/update-apply"
                  onsubmit="return confirm('<?= e(__('admin.maintenance.update_confirm')) ?>')">
                <?= csrf_field() ?>
                <button class="btn btn--primary"><?= e(__('admin.maintenance.update_now')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (is_array($updateInfo)): ?>
        <?php if (!($updateInfo['ok'] ?? false)): ?>
            <p class="alert alert--error"><?= e(__('admin.maintenance.update_check_failed')) ?> — <?= e((string) ($updateInfo['error'] ?? '?')) ?></p>
        <?php elseif (!empty($updateInfo['up_to_date'])): ?>
            <p class="alert alert--success">
                <?= e(__('admin.maintenance.update_up_to_date')) ?>
                <?php if (!empty($updateInfo['short'])): ?>
                    (<code><?= e($updateInfo['short']) ?></code>)
                <?php endif; ?>
            </p>
        <?php else: ?>
            <dl class="kv">
                <dt><?= e(__('admin.maintenance.update_local')) ?></dt>
                <dd>
                    <?php if (!empty($updateInfo['local'])): ?>
                        <code><?= e(substr($updateInfo['local'], 0, 7)) ?></code>
                    <?php else: ?>
                        <span class="muted"><?= e(__('admin.maintenance.update_unknown_local')) ?></span>
                    <?php endif; ?>
                </dd>
                <dt><?= e(__('admin.maintenance.update_remote')) ?></dt>
                <dd>
                    <a href="<?= e($updateInfo['commit_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <code><?= e($updateInfo['short']) ?></code>
                    </a>
                    <?php if (!empty($updateInfo['behind'])): ?>
                        · <?= (int) $updateInfo['behind'] ?> <?= e(__('admin.maintenance.update_commits_behind')) ?>
                    <?php endif; ?>
                </dd>
                <?php if (!empty($updateInfo['message'])): ?>
                    <dt><?= e(__('admin.maintenance.update_message')) ?></dt>
                    <dd><?= e((string) strtok($updateInfo['message'], "\n")) ?></dd>
                <?php endif; ?>
                <?php if (!empty($updateInfo['committed_at'])): ?>
                    <dt><?= e(__('admin.maintenance.update_committed_at')) ?></dt>
                    <dd class="muted"><?= e($updateInfo['committed_at']) ?></dd>
                <?php endif; ?>
            </dl>
            <?php if (empty($updateInfo['is_git'])): ?>
                <p class="alert alert--warn"><?= e(__('admin.maintenance.update_not_git')) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($updateHistory !== []): ?>
        <h3 class="section__subtitle" style="margin-top:16px;font-size:15px"><?= e(__('admin.maintenance.update_history')) ?></h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('admin.maintenance.when')) ?></th>
                    <th><?= e(__('admin.maintenance.update_from')) ?></th>
                    <th><?= e(__('admin.maintenance.update_to')) ?></th>
                    <th><?= e(__('admin.maintenance.update_status')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($updateHistory as $u): ?>
                    <tr>
                        <td class="muted"><?= e((string) $u['created_at']) ?></td>
                        <td><?php if (!empty($u['from_commit'])): ?><code><?= e(substr((string) $u['from_commit'], 0, 7)) ?></code><?php endif; ?></td>
                        <td><code><?= e(substr((string) $u['to_commit'], 0, 7)) ?></code></td>
                        <td>
                            <?php if ($u['status'] === 'success'): ?>
                                <span class="badge badge--ok">OK</span>
                                <?php if ((int) $u['migrations_run'] > 0): ?>
                                    · <?= (int) $u['migrations_run'] ?> migrations
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge--fail">FAIL</span>
                                <?php if (!empty($u['error'])): ?>
                                    <br><span class="muted"><?= e((string) $u['error']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.mode')) ?></h2>
    <form method="post" action="/admin/maintenance/mode" class="section-card--inline" style="padding:0">
        <?= csrf_field() ?>
        <label class="checkbox">
            <input type="checkbox" name="enabled" value="1" <?= $maintenanceMode ? 'checked' : '' ?>>
            <?= e(__('admin.maintenance.mode_enable')) ?>
        </label>
        <button type="submit" class="btn btn--primary btn--sm"><?= e(__('admin.save')) ?></button>
    </form>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.cleanup')) ?></h2>
    <div class="btn-row">
        <form method="post" action="/admin/maintenance/clear-cache">
            <?= csrf_field() ?><button class="btn btn--ghost"><?= e(__('admin.maintenance.clear_cache')) ?></button>
        </form>
        <form method="post" action="/admin/maintenance/clear-logs">
            <?= csrf_field() ?><button class="btn btn--ghost"><?= e(__('admin.maintenance.clear_logs')) ?></button>
        </form>
    </div>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.backups')) ?></h2>
    <form method="post" action="/admin/maintenance/backup" style="margin-bottom:14px">
        <?= csrf_field() ?><button class="btn btn--primary"><?= e(__('admin.maintenance.backup_now')) ?></button>
    </form>
    <?php if ($backups === []): ?>
        <p class="section__intro"><?= e(__('admin.maintenance.no_backups')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(__('admin.maintenance.file')) ?></th><th><?= e(__('admin.maintenance.size')) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><?= e($b['name']) ?><br><span class="muted"><?= e(date('Y-m-d H:i', $b['created'])) ?></span></td>
                            <td><?= e((string) round($b['size'] / 1024)) ?> KB</td>
                            <td class="row-actions">
                                <a class="btn btn--ghost btn--sm" href="/admin/maintenance/backup/<?= e(rawurlencode($b['name'])) ?>"><?= e(__('admin.maintenance.download')) ?></a>
                                <form method="post" action="/admin/maintenance/backup-delete" onsubmit="return confirm('?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                                    <button class="btn btn--danger btn--sm"><?= e(__('admin.users.delete')) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.system_info')) ?></h2>
    <dl class="kv">
        <dt>PHP</dt><dd><?= e($system['php_version']) ?></dd>
        <dt>MySQL</dt><dd><?= e($system['mysql_version']) ?></dd>
        <dt><?= e(__('admin.about.version')) ?></dt><dd><?= e($system['app_version']) ?></dd>
        <dt><?= e(__('admin.maintenance.extensions')) ?></dt><dd><?= (int) $system['extensions'] ?></dd>
        <dt><?= e(__('admin.maintenance.uploads_size')) ?></dt><dd><?= e($system['uploads_size']) ?></dd>
        <dt><?= e(__('admin.maintenance.db_size')) ?></dt><dd><?= e($system['db_size']) ?></dd>
        <dt><?= e(__('admin.maintenance.disk_free')) ?></dt><dd><?= e($system['disk_free']) ?></dd>
    </dl>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.maintenance.recent_activity')) ?></h2>
    <?php if ($activity === []): ?>
        <p class="section__intro"><?= e(__('admin.maintenance.no_activity')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(__('admin.maintenance.when')) ?></th><th><?= e(__('admin.maintenance.action')) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($activity as $a): ?>
                        <tr><td class="muted"><?= e((string) $a['created_at']) ?></td><td><?= e((string) $a['action']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
