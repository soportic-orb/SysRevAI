<?php

declare(strict_types=1);

use SysRevAI\Core\Config;

$host     = (string) (setting('smtp.host') ?? '');
$port     = (int) (setting('smtp.port') ?? 587);
$username = (string) (setting('smtp.username') ?? '');
$hasPass  = Config::hasEncrypted('smtp.password');
$enc      = (string) (setting('smtp.encryption') ?? 'tls');
$fromMail = (string) (setting('smtp.from_email') ?? '');
$fromName = (string) (setting('smtp.from_name') ?? 'SysRevAI');
$events   = ['conflict', 'invitation', 'error', 'weekly'];
?>
<h1 class="section__title"><?= e(__('admin.sections.email')) ?></h1>

<form method="post" action="/admin/settings/email" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="host"><?= e(__('admin.email.host')) ?></label>
            <input class="input" id="host" name="host" value="<?= e($host) ?>">
        </div>
        <div class="field field--narrow">
            <label class="field-label" for="port"><?= e(__('admin.email.port')) ?></label>
            <input class="input" id="port" name="port" type="number" value="<?= $port ?>">
        </div>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="username"><?= e(__('admin.email.username')) ?></label>
            <input class="input" id="username" name="username" value="<?= e($username) ?>" autocomplete="off">
        </div>
        <div class="field">
            <label class="field-label" for="password"><?= e(__('admin.email.password')) ?></label>
            <input class="input" id="password" name="password" type="password" autocomplete="off"
                   placeholder="<?= $hasPass ? '••••••••  (' . e(__('admin.claude.key_set')) . ')' : '' ?>">
        </div>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="encryption"><?= e(__('admin.email.encryption')) ?></label>
            <select class="select" id="encryption" name="encryption">
                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $v => $n): ?>
                    <option value="<?= $v ?>" <?= $enc === $v ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="from_name"><?= e(__('admin.email.from_name')) ?></label>
            <input class="input" id="from_name" name="from_name" value="<?= e($fromName) ?>">
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="from_email"><?= e(__('admin.email.from_email')) ?></label>
        <input class="input" id="from_email" name="from_email" type="email" value="<?= e($fromMail) ?>">
    </div>

    <fieldset class="toggles">
        <legend><?= e(__('admin.email.notifications')) ?></legend>
        <?php foreach ($events as $ev): ?>
            <label class="checkbox">
                <input type="checkbox" name="notify[<?= $ev ?>]" value="1" <?= (bool) (setting('notify.' . $ev) ?? false) ? 'checked' : '' ?>>
                <?= e(__('admin.email.notify_' . $ev)) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>

<form method="post" action="/admin/settings/email/test" class="section-card">
    <?= csrf_field() ?>
    <label class="field-label" for="test_email"><?= e(__('admin.email.test_label')) ?></label>
    <div class="section-card--inline" style="padding:0;margin-top:6px">
        <input class="input" id="test_email" name="test_email" type="email" placeholder="you@example.com">
        <button type="submit" class="btn btn--ghost"><?= e(__('admin.email.send_test')) ?></button>
    </div>
</form>
