<?php

declare(strict_types=1);

$reasons = (array) (setting('reviews.default_exclusion_reasons') ?? ['Wrong population', 'Wrong intervention', 'Wrong comparator', 'Wrong outcome', 'Wrong study design', 'Duplicate', 'Full text not available']);
$tools   = (array) (setting('reviews.rob_tools') ?? ['rob2', 'robins_i']);
$dbl     = (bool) (setting('reviews.double_reviewer_default') ?? true);
$confRes = (string) (setting('reviews.conflict_resolution') ?? 'manual');

$allTools = ['rob2' => 'RoB 2', 'robins_i' => 'ROBINS-I', 'newcastle_ottawa' => 'Newcastle-Ottawa', 'jbi' => 'JBI'];
?>
<h1 class="section__title"><?= e(__('admin.sections.reviews')) ?></h1>
<p class="section__intro"><?= e(__('admin.reviews.intro')) ?></p>

<form method="post" action="/admin/settings/reviews" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field-label" for="default_exclusion_reasons"><?= e(__('admin.reviews.exclusion_reasons')) ?></label>
        <textarea class="input" id="default_exclusion_reasons" name="default_exclusion_reasons" rows="7"><?= e(implode("\n", $reasons)) ?></textarea>
        <span class="field-help"><?= e(__('admin.reviews.one_per_line')) ?></span>
    </div>

    <fieldset class="toggles">
        <legend><?= e(__('admin.reviews.rob_tools')) ?></legend>
        <?php foreach ($allTools as $id => $name): ?>
            <label class="checkbox">
                <input type="checkbox" name="rob_tools[]" value="<?= $id ?>" <?= in_array($id, $tools, true) ? 'checked' : '' ?>>
                <?= e($name) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <label class="checkbox">
        <input type="checkbox" name="double_reviewer_default" value="1" <?= $dbl ? 'checked' : '' ?>>
        <?= e(__('admin.reviews.double_default')) ?>
    </label>

    <div class="field">
        <label class="field-label" for="conflict_resolution"><?= e(__('admin.reviews.conflict_resolution')) ?></label>
        <select class="select" id="conflict_resolution" name="conflict_resolution">
            <option value="manual" <?= $confRes === 'manual' ? 'selected' : '' ?>><?= e(__('admin.reviews.conflict_manual')) ?></option>
            <option value="third" <?= $confRes === 'third' ? 'selected' : '' ?>><?= e(__('admin.reviews.conflict_third')) ?></option>
        </select>
    </div>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
