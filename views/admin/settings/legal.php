<?php

declare(strict_types=1);

$privacy = (string) (setting('legal.privacy_policy') ?? '');
$terms   = (string) (setting('legal.terms_of_use') ?? '');
?>
<h1 class="section__title"><?= e(__('admin.sections.legal')) ?></h1>
<p class="section__intro"><?= e(__('admin.legal.intro')) ?></p>

<form method="post" action="/admin/settings/legal" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field-label" for="privacy_policy"><?= e(__('admin.legal.privacy_label')) ?></label>
        <textarea class="input legal-editor" id="privacy_policy" name="privacy_policy"
                  rows="14" spellcheck="false"><?= e($privacy) ?></textarea>
        <span class="field-help"><?= e(__('admin.legal.html_help')) ?></span>
        <?php if ($privacy !== ''): ?>
            <p class="field-help">
                <a class="link-ext" href="/privacy" target="_blank" rel="noopener noreferrer">
                    /privacy &rarr;
                </a>
            </p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field-label" for="terms_of_use"><?= e(__('admin.legal.terms_label')) ?></label>
        <textarea class="input legal-editor" id="terms_of_use" name="terms_of_use"
                  rows="14" spellcheck="false"><?= e($terms) ?></textarea>
        <span class="field-help"><?= e(__('admin.legal.html_help')) ?></span>
        <?php if ($terms !== ''): ?>
            <p class="field-help">
                <a class="link-ext" href="/terms" target="_blank" rel="noopener noreferrer">
                    /terms &rarr;
                </a>
            </p>
        <?php endif; ?>
    </div>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
