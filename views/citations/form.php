<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var string[] $styles      list of style keys */
/** @var string   $style       currently selected style */
/** @var string   $text        textarea contents (preserved across runs) */
/** @var array<int,array{original:string,normalized:string}> $results  optional, set after convert() */
/** @var array    $openReviews user's active reviews */
/** @var bool     $autorun     auto-submit on load (set when arriving from the Review flow) */

$results ??= [];
$hasResults = $results !== [];
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('citations.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('citations.intro')) ?></p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <!-- Paste box + style picker. Carries data-ai-action so the global
         "AI is working" overlay rises during the Claude call. -->
    <form method="post" action="/citations/convert"
          class="form-grid section-card"
          id="citationConvertForm"
          data-ai-action>
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="citationStyle"><?= e(__('citations.style_label')) ?></label>
            <select class="select" id="citationStyle" name="style">
                <?php foreach ($styles as $s): ?>
                    <option value="<?= e($s) ?>" <?= $style === $s ? 'selected' : '' ?>>
                        <?= e(__('citations.style_' . $s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="citationText"><?= e(__('citations.paste_label')) ?></label>
            <textarea class="input"
                      id="citationText"
                      name="text"
                      rows="12"
                      placeholder="<?= e(__('citations.paste_placeholder')) ?>"><?= e($text) ?></textarea>
            <span class="field-help"><?= e(__('citations.paste_help')) ?></span>
        </div>

        <div>
            <button type="submit" class="btn btn--primary"
                    data-busy-label="<?= e(__('common.working')) ?>">
                <?= e(__('citations.convert_btn')) ?>
            </button>
        </div>
    </form>

    <?php if ($hasResults): ?>
        <h2 class="section__subtitle citations-results-title">
            <?= e(__('citations.result_title', count($results))) ?>
        </h2>

        <!-- Same HTML5 form-association trick as the search results
             table: the bulk form holds only the toolbar; row checkboxes
             carry form="citationImportForm" so they post together. -->
        <form method="post" action="/citations/import"
              id="citationImportForm"
              class="section-card search-bulk-card"
              data-ai-action>
            <?= csrf_field() ?>

            <div class="search-bulk-toolbar">
                <label class="checkbox search-bulk-toolbar__all">
                    <input type="checkbox" id="citationSelectAll" checked>
                    <?= e(__('search.select_all')) ?>
                </label>
                <span class="muted search-bulk-toolbar__count" id="citationSelectedCount"><?= count($results) ?></span>
                <label class="field-label search-bulk-toolbar__label" for="citationReviewId">
                    <?= e(__('search.import_target_label')) ?>
                </label>
                <select class="select select--sm"
                        id="citationReviewId"
                        name="review_id"
                        <?= $openReviews === [] ? 'disabled' : '' ?>>
                    <option value=""><?= e(__('search.import_target_placeholder')) ?></option>
                    <?php foreach ($openReviews as $rv): ?>
                        <option value="<?= (int) $rv['id'] ?>"><?= e((string) $rv['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--primary btn--sm"
                        id="citationImportBtn"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    <?= e(__('search.import_selected')) ?>
                </button>
            </div>
        </form>

        <?php if ($openReviews === []): ?>
            <div class="alert alert--warn" data-no-toast>
                <?= e(__('search.no_open_reviews')) ?>
            </div>
        <?php endif; ?>

        <div class="section-card" style="padding:0; margin-top: 12px">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th class="search-external-table__check"></th>
                        <th><?= e(__('citations.col_normalized')) ?></th>
                        <th><?= e(__('citations.col_original')) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td class="search-external-table__check">
                                    <input type="checkbox"
                                           class="citation-row-check"
                                           form="citationImportForm"
                                           name="selected[]"
                                           value="<?= e((string) $row['normalized']) ?>"
                                           checked
                                           aria-label="<?= e(__('search.select_row')) ?>">
                                </td>
                                <td class="citations-normalized"><?= e((string) $row['normalized']) ?></td>
                                <td class="muted citations-original"><?= e((string) $row['original']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';

    <?php if ($autorun): ?>
    /* Arrived from the Review hand-off — auto-submit the convert form so
       the user lands directly on the result list. The AI overlay raised
       by data-ai-action covers the round-trip. */
    var f = document.getElementById('citationConvertForm');
    if (f && document.getElementById('citationText') &&
            document.getElementById('citationText').value.trim() !== '') {
        setTimeout(function () { f.requestSubmit(); }, 50);
    }
    <?php endif; ?>

    <?php if ($hasResults): ?>
    var all      = document.getElementById('citationSelectAll');
    var counter  = document.getElementById('citationSelectedCount');
    var btn      = document.getElementById('citationImportBtn');
    var reviewEl = document.getElementById('citationReviewId');
    var rows     = document.querySelectorAll('.citation-row-check');

    function recompute() {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        counter.textContent = String(n);
        btn.disabled = n === 0 || !reviewEl.value;
    }
    all.addEventListener('change', function () {
        rows.forEach(function (c) { c.checked = all.checked; });
        recompute();
    });
    rows.forEach(function (c) {
        c.addEventListener('change', function () {
            if (!c.checked) all.checked = false;
            recompute();
        });
    });
    reviewEl.addEventListener('change', recompute);
    recompute();
    <?php endif; ?>
})();
</script>
