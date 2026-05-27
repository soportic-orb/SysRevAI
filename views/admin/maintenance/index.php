<?php

declare(strict_types=1);

/** @var bool $maintenanceMode */
/** @var array $system */
/** @var array $backups */
/** @var array $activity */
?>
<h1 class="section__title"><?= e(__('admin.sections.maintenance')) ?></h1>

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
