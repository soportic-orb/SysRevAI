<?php

declare(strict_types=1);

/** @var array<string,array{installed:bool,path:?string,version:?string,manual_hint:string}> $dependencies */

$maxMb    = (int) (setting('files.max_pdf_mb') ?? 50);
$types    = (array) (setting('files.allowed_types') ?? ['pdf']);
$location = (string) (setting('files.upload_location') ?? 'docroot');
$compress = (bool) (setting('files.compress') ?? false);
$ocr      = (bool) (setting('files.ocr') ?? false);
$allTypes = ['pdf', 'doc', 'docx', 'txt'];

$dependencies = $dependencies ?? [];
$gsInstalled = (bool) ($dependencies['ghostscript']['installed'] ?? false);
$tsInstalled = (bool) ($dependencies['tesseract']['installed']   ?? false);
?>
<h1 class="section__title"><?= e(__('admin.sections.files')) ?></h1>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.files.deps_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.files.deps_intro')) ?></p>

    <div class="dep-list">
        <?php foreach (['ghostscript' => __('admin.files.dep_ghostscript'), 'tesseract' => __('admin.files.dep_tesseract')] as $key => $label):
            $dep = $dependencies[$key] ?? ['installed' => false, 'version' => null, 'path' => null, 'manual_hint' => ''];
        ?>
            <div class="dep-row">
                <div class="dep-row__label">
                    <strong><?= e((string) $label) ?></strong>
                    <?php if ($dep['installed']): ?>
                        <span class="badge badge--ok"><?= e(__('admin.files.dep_installed')) ?></span>
                        <?php if (!empty($dep['version'])): ?>
                            <span class="muted"><?= e((string) $dep['version']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge--fail"><?= e(__('admin.files.dep_missing')) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!$dep['installed']): ?>
                    <form method="post" action="/admin/settings/files/install" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="package" value="<?= e($key) ?>">
                        <button class="btn btn--sm btn--primary"
                                onclick="this.disabled=true;this.form.submit();">
                            <?= e(__('admin.files.dep_install_btn')) ?>
                        </button>
                    </form>
                    <code class="muted"><?= e((string) ($dep['manual_hint'] ?? '')) ?></code>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="field-help"><?= e(__('admin.files.deps_install_help')) ?></p>
</div>

<form method="post" action="/admin/settings/files" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="max_pdf_mb"><?= e(__('admin.files.max_pdf_mb')) ?></label>
            <input class="input" id="max_pdf_mb" name="max_pdf_mb" type="number" min="1" value="<?= $maxMb ?>">
        </div>
        <div class="field">
            <label class="field-label" for="upload_location"><?= e(__('admin.files.location')) ?></label>
            <select class="select" id="upload_location" name="upload_location">
                <option value="docroot" <?= $location === 'docroot' ? 'selected' : '' ?>><?= e(__('admin.files.location_docroot')) ?></option>
                <option value="outside" <?= $location === 'outside' ? 'selected' : '' ?>><?= e(__('admin.files.location_outside')) ?></option>
            </select>
        </div>
    </div>

    <fieldset class="toggles">
        <legend><?= e(__('admin.files.allowed_types')) ?></legend>
        <div class="checkbox-grid">
            <?php foreach ($allTypes as $t): ?>
                <label class="checkbox">
                    <input type="checkbox" name="allowed_types[]" value="<?= $t ?>" <?= in_array($t, $types, true) ? 'checked' : '' ?>>
                    <?= strtoupper($t) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <label class="checkbox <?= $gsInstalled ? '' : 'is-disabled' ?>">
        <input type="checkbox" name="compress" value="1"
               <?= $compress && $gsInstalled ? 'checked' : '' ?>
               <?= $gsInstalled ? '' : 'disabled' ?>>
        <?= e(__('admin.files.compress')) ?>
        <?php if (!$gsInstalled): ?>
            <span class="field-help muted">— <?= e(__('admin.files.needs_ghostscript')) ?></span>
        <?php endif; ?>
    </label>
    <label class="checkbox <?= $tsInstalled ? '' : 'is-disabled' ?>">
        <input type="checkbox" name="ocr" value="1"
               <?= $ocr && $tsInstalled ? 'checked' : '' ?>
               <?= $tsInstalled ? '' : 'disabled' ?>>
        <?= e(__('admin.files.ocr')) ?>
        <?php if (!$tsInstalled): ?>
            <span class="field-help muted">— <?= e(__('admin.files.needs_tesseract')) ?></span>
        <?php endif; ?>
    </label>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
