<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $openReviews */
/** @var int   $cap */
?>
<div class="page page--narrow">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('verification.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('verification.intro', $cap)) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="/tools/verify-citations/run"
          class="form-grid section-card" data-ai-action>
        <?= csrf_field() ?>

        <fieldset class="toggles">
            <legend><?= e(__('verification.source')) ?></legend>
            <label class="checkbox">
                <input type="radio" name="source" value="paste" checked
                       data-verif-source>
                <?= e(__('verification.source_paste')) ?>
            </label>
            <label class="checkbox">
                <input type="radio" name="source" value="review"
                       data-verif-source
                       <?= $openReviews === [] ? 'disabled' : '' ?>>
                <?= e(__('verification.source_review')) ?>
            </label>
        </fieldset>

        <div class="field" data-verif-pane="paste">
            <label class="field-label" for="paste"><?= e(__('verification.paste_label')) ?></label>
            <textarea class="input" id="paste" name="paste" rows="8"
                      placeholder="<?= e(__('verification.paste_placeholder')) ?>"></textarea>
            <span class="field-help"><?= e(__('verification.paste_help', $cap)) ?></span>
        </div>

        <div class="field" data-verif-pane="review" hidden>
            <label class="field-label" for="review_id"><?= e(__('verification.review_label')) ?></label>
            <select class="select" id="review_id" name="review_id">
                <option value=""><?= e(__('verification.review_placeholder')) ?></option>
                <?php foreach ($openReviews as $rv): ?>
                    <option value="<?= (int) $rv['id'] ?>"><?= e((string) $rv['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="field-help"><?= e(__('verification.review_help', $cap)) ?></span>
        </div>

        <div>
            <button type="submit" class="btn btn--primary"
                    data-busy-label="<?= e(__('common.working')) ?>">
                <?= e(__('verification.run_btn')) ?>
            </button>
        </div>
    </form>
</div>

<script>
/* Toggle the paste vs. review panes when the source radio changes. */
(function () {
    'use strict';
    var radios = document.querySelectorAll('[data-verif-source]');
    var panes  = document.querySelectorAll('[data-verif-pane]');
    function apply(value) {
        panes.forEach(function (p) {
            p.hidden = p.getAttribute('data-verif-pane') !== value;
        });
    }
    radios.forEach(function (r) {
        r.addEventListener('change', function () { if (r.checked) apply(r.value); });
    });
    var checked = document.querySelector('[data-verif-source]:checked');
    if (checked) apply(checked.value);
})();
</script>
