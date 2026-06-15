<?php

declare(strict_types=1);

use SysRevAI\Services\RegistrationFields;

/** @var array                   $review     Review row. */
/** @var string                  $kind       'prospero' | 'osf'. */
/** @var array<int,array>        $schema     RegistrationFields::schemaFor($kind). */
/** @var array<string,string>    $data       Saved field values keyed by id. */
/** @var ?string                 $updated_at Last-saved timestamp. */

$reviewId = (int) $review['id'];
$registry = $kind === RegistrationFields::KIND_OSF ? 'OSF' : 'PROSPERO';
$registryFull = $kind === RegistrationFields::KIND_OSF
    ? __('registration.osf_full')
    : __('registration.prospero_full');
?>
<div class="page review-registration-page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <?= e(__('registration.title')) ?>
                <span class="muted">— <?= e($registry) ?></span>
            </h1>
            <p class="page__subtitle muted">
                <?= e(sprintf(__('registration.subtitle'), $registryFull, (string) $review['title'])) ?>
            </p>
        </div>
        <div class="btn-row">
            <a class="btn btn--ghost btn--sm" href="/reviews/<?= $reviewId ?>/exports">
                ← <?= e(__('registration.back_to_exports')) ?>
            </a>
        </div>
    </div>

    <form id="registrationForm" class="review-registration"
          method="post" action="/reviews/<?= $reviewId ?>/exports/registration/save"
          data-csrf="<?= e(csrf_token()) ?>"
          data-review-id="<?= $reviewId ?>">
        <?= csrf_field() ?>

        <section class="section-card review-registration__toolbar">
            <div class="review-registration__toolbar-actions">
                <button type="button" id="registrationAiFill"
                        class="btn btn--primary btn--sm"
                        data-busy-label="<?= e(__('registration.ai_filling')) ?>"
                        title="<?= e(__('registration.ai_fill_title')) ?>">
                    &#10024; <?= e(__('registration.ai_fill')) ?>
                </button>
                <button type="submit" id="registrationSave" class="btn btn--ghost btn--sm">
                    <?= e(__('registration.save')) ?>
                </button>
                <span class="review-registration__status muted" id="registrationStatus">
                    <?php if ($updated_at !== null): ?>
                        <?= e(sprintf(__('registration.saved_at'), (string) $updated_at)) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="review-registration__toolbar-exports">
                <a class="btn btn--ghost btn--sm" id="registrationExportWord"
                   href="/reviews/<?= $reviewId ?>/exports/registration/word"
                   title="<?= e(__('registration.export_word_title')) ?>">
                    <?= e(__('registration.export_word')) ?>
                </a>
                <a class="btn btn--ghost btn--sm" id="registrationExportPdf"
                   href="/reviews/<?= $reviewId ?>/exports/registration/pdf"
                   title="<?= e(__('registration.export_pdf_title')) ?>">
                    <?= e(__('registration.export_pdf')) ?>
                </a>
            </div>
        </section>

        <section class="section-card">
            <p class="muted review-registration__hint">
                <?= e(__('registration.hint_' . $kind)) ?>
            </p>

            <?php foreach ($schema as $f): ?>
                <?php
                    $id = 'reg_' . $f['id'];
                    $value = (string) ($data[$f['id']] ?? '');
                    $labelKey = 'registration.fields.' . $f['label'];
                    $labelText = __($labelKey);
                    if ($labelText === $labelKey) {
                        $labelText = ucfirst(str_replace('_', ' ', $f['id']));
                    }
                    $hintKey = 'registration.hints.' . $f['id'];
                    $hintText = __($hintKey);
                    if ($hintText === $hintKey) {
                        $hintText = '';
                    }
                ?>
                <div class="form-field review-registration__field">
                    <label for="<?= e($id) ?>"><?= e($labelText) ?></label>
                    <?php if ($hintText !== ''): ?>
                        <small class="muted"><?= e($hintText) ?></small>
                    <?php endif; ?>
                    <?php if ($f['type'] === 'textarea'): ?>
                        <textarea id="<?= e($id) ?>" name="fields[<?= e($f['id']) ?>]"
                                  class="input"
                                  rows="<?= (int) ($f['rows'] ?? 3) ?>"
                        ><?= e($value) ?></textarea>
                    <?php else: ?>
                        <input id="<?= e($id) ?>" name="fields[<?= e($f['id']) ?>]"
                               class="input" type="text" value="<?= e($value) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="btn-row">
                <button type="submit" class="btn btn--primary">
                    <?= e(__('registration.save')) ?>
                </button>
            </div>
        </section>
    </form>
</div>

<script>
(function () {
    'use strict';
    var form = document.getElementById('registrationForm');
    if (!form) return;
    var csrf = form.getAttribute('data-csrf');
    var reviewId = form.getAttribute('data-review-id');
    var saveUrl   = '/reviews/' + reviewId + '/exports/registration/save';
    var aiUrl     = '/reviews/' + reviewId + '/exports/registration/ai-fill';
    var statusEl  = document.getElementById('registrationStatus');
    var aiBtn     = document.getElementById('registrationAiFill');
    var saveBtn   = document.getElementById('registrationSave');
    var labels = {
        saving:  <?= json_encode(__('registration.saving')) ?>,
        saved:   <?= json_encode(__('registration.saved')) ?>,
        error:   <?= json_encode(__('registration.error')) ?>,
        confirm: <?= json_encode(__('registration.ai_confirm')) ?>
    };

    function collectFields() {
        var fields = {};
        form.querySelectorAll('[name^="fields["]').forEach(function (el) {
            var m = el.name.match(/^fields\[(.+)\]$/);
            if (m) fields[m[1]] = el.value || '';
        });
        return fields;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveNow();
    });

    function saveNow() {
        statusEl.textContent = labels.saving;
        saveBtn.disabled = true;
        return fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf, fields: collectFields() })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            statusEl.textContent = (body && body.ok) ? labels.saved : labels.error;
        })
        .catch(function () { statusEl.textContent = labels.error; })
        .finally(function () { saveBtn.disabled = false; });
    }

    aiBtn.addEventListener('click', function () {
        if (!window.confirm(labels.confirm)) return;
        var originalLabel = aiBtn.textContent;
        aiBtn.disabled = true;
        aiBtn.textContent = aiBtn.getAttribute('data-busy-label') || originalLabel;
        statusEl.textContent = labels.saving;
        fetch(aiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            if (!body || !body.ok || !body.data) {
                statusEl.textContent = labels.error;
                window.alert(labels.error);
                return;
            }
            // Only populate fields that were empty so we never trample
            // text the user has already polished.
            Object.keys(body.data).forEach(function (k) {
                var el = form.querySelector('[name="fields[' + k + ']"]');
                if (el && el.value.trim() === '' && typeof body.data[k] === 'string') {
                    el.value = body.data[k];
                }
            });
            statusEl.textContent = labels.saved;
        })
        .catch(function () { statusEl.textContent = labels.error; })
        .finally(function () {
            aiBtn.disabled = false;
            aiBtn.textContent = originalLabel;
        });
    });

    // Flush pending edits before the export download navigates, so the
    // generated .docx / .pdf reflects what the user sees on screen.
    ['registrationExportWord', 'registrationExportPdf'].forEach(function (id) {
        var link = document.getElementById(id);
        if (!link) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var href = link.href;
            saveNow().finally(function () { window.location.href = href; });
        });
    });
})();
</script>
