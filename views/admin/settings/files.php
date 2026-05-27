<?php

declare(strict_types=1);

$maxMb    = (int) (setting('files.max_pdf_mb') ?? 50);
$types    = (array) (setting('files.allowed_types') ?? ['pdf']);
$location = (string) (setting('files.upload_location') ?? 'docroot');
$compress = (bool) (setting('files.compress') ?? false);
$ocr      = (bool) (setting('files.ocr') ?? false);
$allTypes = ['pdf', 'doc', 'docx', 'txt'];
?>
<h1 class="section__title"><?= e(__('admin.sections.files')) ?></h1>

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

    <label class="checkbox">
        <input type="checkbox" name="compress" value="1" <?= $compress ? 'checked' : '' ?>>
        <?= e(__('admin.files.compress')) ?>
    </label>
    <label class="checkbox">
        <input type="checkbox" name="ocr" value="1" <?= $ocr ? 'checked' : '' ?>>
        <?= e(__('admin.files.ocr')) ?>
    </label>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
