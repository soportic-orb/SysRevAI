<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Models\Review;

/** @var ?array $review */
/** @var array $pico */
/** @var array $reasons */
/** @var string $formAction */
$isEdit = $review !== null;
$title  = (string) ($review['title'] ?? '');
$mode   = (string) ($review['screening_mode'] ?? 'double_blind');
$pilot  = (int) ($review['pilot_count'] ?? 50);
$reqRev = (int) ($review['reviewers_required'] ?? 2);
?>
<div class="page page--narrow">
    <div class="page__head">
        <h1 class="page__title"><?= e($isEdit ? __('reviews.edit_protocol') : __('reviews.new')) ?></h1>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e($formAction) ?>" class="form-grid section-card">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="title"><?= e(__('reviews.title')) ?></label>
            <input class="input" id="title" name="title" value="<?= e($title) ?>" required>
        </div>

        <div class="field">
            <label class="field-label" for="question"><?= e(__('reviews.question')) ?></label>
            <textarea class="input" id="question" name="question" rows="2"><?= e((string) ($review['question'] ?? '')) ?></textarea>
        </div>

        <fieldset class="toggles">
            <legend><?= e(__('reviews.pico')) ?></legend>
            <?php foreach (['population', 'intervention', 'comparison', 'outcome', 'study_design'] as $f): ?>
                <div class="field">
                    <label class="field-label" for="<?= $f ?>"><?= e(__('reviews.pico_' . $f)) ?></label>
                    <input class="input" id="<?= $f ?>" name="<?= $f ?>" value="<?= e((string) $pico[$f]) ?>">
                </div>
            <?php endforeach; ?>
        </fieldset>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="inclusion_criteria"><?= e(__('reviews.inclusion')) ?></label>
                <textarea class="input" id="inclusion_criteria" name="inclusion_criteria" rows="5"><?= e((string) ($review['inclusion_criteria'] ?? '')) ?></textarea>
            </div>
            <div class="field">
                <label class="field-label" for="exclusion_criteria"><?= e(__('reviews.exclusion')) ?></label>
                <textarea class="input" id="exclusion_criteria" name="exclusion_criteria" rows="5"><?= e((string) ($review['exclusion_criteria'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="screening_mode"><?= e(__('reviews.screening_mode')) ?></label>
            <select class="select" id="screening_mode" name="screening_mode">
                <?php foreach (Review::SCREENING_MODES as $m): ?>
                    <option value="<?= $m ?>" <?= $mode === $m ? 'selected' : '' ?>><?= e(__('reviews.mode_' . $m)) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="field-help"><?= e(__('reviews.mode_help')) ?></span>
        </div>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="reviewers_required"><?= e(__('reviews.reviewers_required')) ?></label>
                <input class="input" id="reviewers_required" name="reviewers_required" type="number" min="1" max="5" value="<?= $reqRev ?>">
            </div>
            <div class="field">
                <label class="field-label" for="pilot_count"><?= e(__('reviews.pilot_count')) ?></label>
                <input class="input" id="pilot_count" name="pilot_count" type="number" min="1" value="<?= $pilot ?>">
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="exclusion_reasons"><?= e(__('reviews.exclusion_reasons')) ?></label>
            <textarea class="input" id="exclusion_reasons" name="exclusion_reasons" rows="6"><?= e(implode("\n", $reasons)) ?></textarea>
            <span class="field-help"><?= e(__('reviews.one_per_line')) ?></span>
        </div>

        <div class="actions actions--start">
            <button type="submit" class="btn btn--primary"><?= e($isEdit ? __('admin.save') : __('reviews.create')) ?></button>
            <a class="btn btn--ghost" href="<?= $isEdit ? '/reviews/' . (int) $review['id'] : '/reviews' ?>"><?= e(__('reviews.cancel')) ?></a>
        </div>
    </form>
</div>
