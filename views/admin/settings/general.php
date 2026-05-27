<?php

declare(strict_types=1);

$siteName = (string) (setting('site.name') ?? 'SysRevAI');
$locale   = (string) (setting('app.locale') ?? config('app.locale', 'ca'));
$timezone = (string) (setting('app.timezone') ?? config('app.timezone', 'Europe/Madrid'));
$accent   = (string) (setting('ui.accent_color') ?? '#0072b2');
$theme    = (string) (setting('ui.theme') ?? 'light');
$footer   = (string) (setting('site.footer_text') ?? '');
$branding = (bool) (setting('site.show_branding') ?? true);
?>
<h1 class="section__title"><?= e(__('admin.sections.general')) ?></h1>

<form method="post" action="/admin/settings/general" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field-label" for="site_name"><?= e(__('admin.general.site_name')) ?></label>
        <input class="input" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="default_locale"><?= e(__('admin.general.default_locale')) ?></label>
            <select class="select" id="default_locale" name="default_locale">
                <?php foreach (['ca' => 'Català', 'es' => 'Español', 'en' => 'English'] as $c => $n): ?>
                    <option value="<?= $c ?>" <?= $locale === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="timezone"><?= e(__('admin.general.timezone')) ?></label>
            <input class="input" id="timezone" name="timezone" value="<?= e($timezone) ?>">
        </div>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="accent_color"><?= e(__('admin.general.accent_color')) ?></label>
            <input class="input input--color" id="accent_color" name="accent_color" type="color" value="<?= e($accent) ?>">
        </div>
        <div class="field">
            <label class="field-label" for="theme"><?= e(__('admin.general.theme')) ?></label>
            <select class="select" id="theme" name="theme">
                <?php foreach (['light', 'dark', 'auto'] as $t): ?>
                    <option value="<?= $t ?>" <?= $theme === $t ? 'selected' : '' ?>><?= e(__('admin.general.theme_' . $t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="footer_text"><?= e(__('admin.general.footer_text')) ?></label>
        <input class="input" id="footer_text" name="footer_text" value="<?= e($footer) ?>">
    </div>

    <label class="checkbox">
        <input type="checkbox" name="show_branding" value="1" <?= $branding ? 'checked' : '' ?>>
        <?= e(__('admin.general.show_branding')) ?>
    </label>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
