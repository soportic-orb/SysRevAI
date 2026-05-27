<?php

declare(strict_types=1);

$supported = (array) config('supported_locales', ['ca', 'es', 'en']);
$active    = (array) (setting('ui.active_locales') ?? ['ca', 'es', 'en']);
$names = [
    'ca' => 'Català', 'es' => 'Español', 'en' => 'English', 'fr' => 'Français',
    'de' => 'Deutsch', 'pt' => 'Português', 'it' => 'Italiano', 'eu' => 'Euskara', 'gl' => 'Galego',
];
?>
<h1 class="section__title"><?= e(__('admin.sections.languages')) ?></h1>
<p class="section__intro"><?= e(__('admin.languages.intro')) ?></p>

<form method="post" action="/admin/settings/languages" class="form-grid section-card">
    <?= csrf_field() ?>

    <fieldset class="toggles">
        <legend><?= e(__('admin.languages.active')) ?></legend>
        <div class="checkbox-grid">
            <?php foreach ($supported as $l): ?>
                <label class="checkbox">
                    <input type="checkbox" name="active_locales[]" value="<?= e($l) ?>" <?= in_array($l, $active, true) ? 'checked' : '' ?>>
                    <?= e($names[$l] ?? strtoupper($l)) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.languages.editor_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.languages.editor_note')) ?></p>
</div>
