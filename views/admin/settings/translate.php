<?php

declare(strict_types=1);

use SysRevAI\Core\Config;

$projectId = (string) (setting('google.project_id') ?? '');
$active    = (array) (setting('google.active_languages') ?? ['ca', 'es', 'en']);
$cacheOn   = (bool) (setting('google.cache_enabled') ?? true);
$ttl       = (int) (setting('google.cache_ttl_days') ?? 90);
$hasCreds  = (string) (setting('google.credentials_path') ?? '') !== '';
$langs     = ['ca', 'es', 'en', 'fr', 'de', 'pt', 'it'];
?>
<h1 class="section__title"><?= e(__('admin.sections.translate')) ?></h1>
<p class="section__intro"><?= e(__('admin.translate.intro')) ?></p>

<form method="post" action="/admin/settings/translate" class="form-grid section-card" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field-label" for="project_id"><?= e(__('admin.translate.project_id')) ?></label>
        <input class="input" id="project_id" name="project_id" value="<?= e($projectId) ?>">
    </div>

    <div class="field">
        <label class="field-label" for="service_account"><?= e(__('admin.translate.service_account')) ?></label>
        <input class="input" id="service_account" name="service_account" type="file" accept="application/json,.json">
        <span class="field-help">
            <?= $hasCreds ? '&#10003; ' . e(__('admin.translate.creds_set')) : e(__('admin.translate.creds_help')) ?>
        </span>
    </div>

    <fieldset class="toggles">
        <legend><?= e(__('admin.translate.active_languages')) ?></legend>
        <div class="checkbox-grid">
            <?php foreach ($langs as $l): ?>
                <label class="checkbox">
                    <input type="checkbox" name="active_languages[]" value="<?= $l ?>" <?= in_array($l, $active, true) ? 'checked' : '' ?>>
                    <?= strtoupper($l) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="cache_enabled" value="1" <?= $cacheOn ? 'checked' : '' ?>>
                <?= e(__('admin.translate.cache_enabled')) ?>
            </label>
        </div>
        <div class="field">
            <label class="field-label" for="cache_ttl_days"><?= e(__('admin.translate.cache_ttl')) ?></label>
            <input class="input" id="cache_ttl_days" name="cache_ttl_days" type="number" min="1" value="<?= $ttl ?>">
        </div>
    </div>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>

<form method="post" action="/admin/settings/translate/verify" class="section-card section-card--inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn--ghost"><?= e(__('admin.translate.verify')) ?></button>
    <span class="field-help"><?= e(__('admin.translate.free_tier')) ?></span>
</form>
