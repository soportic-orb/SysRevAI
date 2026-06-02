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

// AI-extract endpoint: the existing review has its own URL; new-review
// flow uses the no-id draft endpoint. Either way the response shape is
// identical, so the script block below is the same on both pages.
$extractUrl = $isEdit
    ? '/reviews/' . (int) $review['id'] . '/protocol/extract'
    : '/reviews/extract-protocol-draft';
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e($isEdit ? __('reviews.edit_protocol') : __('reviews.new')) ?></h1>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <!-- Two-column layout: the protocol form lives on the left so the
         reviewer keeps their primary attention there, and the AI-assist
         card sits to the right as an opt-in shortcut. On narrow screens
         the grid collapses and the AI card stacks below the form. -->
    <div class="protocol-form-grid">
        <form method="post" action="<?= e($formAction) ?>"
              class="form-grid section-card protocol-form-grid__form"
              id="protocolForm">
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

        <aside class="section-card ai-upload protocol-form-grid__ai" id="protocolUpload">
            <h2 class="section__subtitle"><?= e(__('reviews.ai_upload_title')) ?></h2>
            <p class="section__intro"><?= e(__('reviews.ai_upload_intro')) ?></p>
            <div class="ai-upload__row">
                <input class="input" type="file" id="protocolFile" name="document"
                       accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <button type="button" class="btn btn--primary" id="extractBtn"
                        data-url="<?= e($extractUrl) ?>"
                        data-csrf="<?= e(csrf_token()) ?>"
                        data-loading="<?= e(__('reviews.ai_upload_running')) ?>">
                    <?= e(__('reviews.ai_upload_analyze')) ?>
                </button>
            </div>
            <p class="ai-upload__status muted" id="extractStatus" hidden></p>
            <p class="field-help"><?= e(__('reviews.ai_upload_help')) ?></p>

            <!-- Detected sub-studies. Hidden until the AI returns a
                 non-empty `secondaries` list. Each card is rendered by JS
                 from #secondaryTemplate and submits straight to /reviews
                 (the regular store endpoint), so creating one extra review
                 from a parent protocol is one click. -->
            <div class="ai-upload__secondaries" id="secondariesContainer" hidden>
                <h3 class="ai-upload__secondaries-title"><?= e(__('reviews.ai_secondaries_title')) ?></h3>
                <p class="muted ai-upload__secondaries-intro"><?= e(__('reviews.ai_secondaries_intro')) ?></p>
                <div id="secondariesList"></div>
            </div>

            <template id="secondaryTemplate">
                <form method="post" action="/reviews" class="secondary-card">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="title" value="">
                    <input type="hidden" name="question" value="">
                    <input type="hidden" name="population" value="">
                    <input type="hidden" name="intervention" value="">
                    <input type="hidden" name="comparison" value="">
                    <input type="hidden" name="outcome" value="">
                    <input type="hidden" name="study_design" value="">
                    <input type="hidden" name="inclusion_criteria" value="">
                    <input type="hidden" name="exclusion_criteria" value="">
                    <h4 class="secondary-card__title"></h4>
                    <p class="secondary-card__question muted"></p>
                    <ul class="secondary-card__pico muted"></ul>
                    <div class="secondary-card__actions">
                        <button type="submit" class="btn btn--primary btn--sm">
                            <?= e(__('reviews.ai_secondary_create')) ?>
                        </button>
                    </div>
                </form>
            </template>
        </aside>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('extractBtn');
    var input = document.getElementById('protocolFile');
    var status = document.getElementById('extractStatus');
    if (!btn || !input || !status) return;

    var labels = {
        ok:           <?= json_encode(__('reviews.ai_upload_ok')) ?>,
        okMulti:      <?= json_encode(__('reviews.ai_upload_ok_multi')) ?>,
        empty:        <?= json_encode(__('reviews.ai_upload_empty')) ?>,
        toobig:       <?= json_encode(__('reviews.ai_upload_too_large')) ?>,
        format:       <?= json_encode(__('reviews.ai_upload_bad_format')) ?>,
        failed:       <?= json_encode(__('reviews.ai_upload_failed')) ?>,
        nofile:       <?= json_encode(__('reviews.ai_upload_pick_first')) ?>,
        picoPop:      <?= json_encode(__('reviews.pico_population')) ?>,
        picoInt:      <?= json_encode(__('reviews.pico_intervention')) ?>,
        picoOut:      <?= json_encode(__('reviews.pico_outcome')) ?>
    };

    var secondariesContainer = document.getElementById('secondariesContainer');
    var secondariesList      = document.getElementById('secondariesList');
    var secondaryTemplate    = document.getElementById('secondaryTemplate');

    function show(msg, ok) {
        status.hidden = false;
        status.textContent = msg;
        status.style.color = ok ? '#06624a' : '#9c3b00';
    }
    function setField(id, value) {
        var el = document.getElementById(id);
        if (!el || typeof value !== 'string' || value === '') return;
        el.value = value;
        el.classList.add('ai-suggested');
        el.addEventListener('input', function () { el.classList.remove('ai-suggested'); }, { once: true });
    }

    /* Pre-fill the main form fields with the primary protocol the AI
       returned. Old-shape responses (flat object) still work — we treat
       the whole thing as the primary so a model regression doesn't break
       the form pre-fill. */
    function applyPrimary(p) {
        if (!p) return;
        setField('title',              p.title);
        setField('question',           p.question);
        setField('population',         p.population);
        setField('intervention',       p.intervention);
        setField('comparison',         p.comparison);
        setField('outcome',            p.outcome);
        setField('study_design',       p.study_design);
        setField('inclusion_criteria', p.inclusion_criteria);
        setField('exclusion_criteria', p.exclusion_criteria);
    }

    /* Render one card per detected sub-study. Each card is a self-contained
       <form> POSTing to /reviews with the AI-extracted fields as hidden
       inputs — submitting it creates a new sibling review without leaving
       this page. */
    function renderSecondaries(list) {
        if (!secondariesContainer || !secondariesList || !secondaryTemplate) return;
        secondariesList.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            secondariesContainer.hidden = true;
            return;
        }
        list.forEach(function (s, idx) {
            var node = secondaryTemplate.content.firstElementChild.cloneNode(true);
            var fields = ['title','question','population','intervention','comparison',
                          'outcome','study_design','inclusion_criteria','exclusion_criteria'];
            fields.forEach(function (f) {
                var input = node.querySelector('input[name="' + f + '"]');
                if (input) input.value = (s && typeof s[f] === 'string') ? s[f] : '';
            });
            node.querySelector('.secondary-card__title').textContent =
                (s && s.title) ? s.title : ('Sub-study #' + (idx + 1));
            node.querySelector('.secondary-card__question').textContent =
                (s && s.question) ? s.question : '';
            var pico = node.querySelector('.secondary-card__pico');
            pico.innerHTML = '';
            [
                [labels.picoPop, s && s.population],
                [labels.picoInt, s && s.intervention],
                [labels.picoOut, s && s.outcome]
            ].forEach(function (pair) {
                if (!pair[1]) return;
                var li = document.createElement('li');
                var strong = document.createElement('strong');
                strong.textContent = pair[0] + ': ';
                li.appendChild(strong);
                li.appendChild(document.createTextNode(pair[1]));
                pico.appendChild(li);
            });
            secondariesList.appendChild(node);
        });
        secondariesContainer.hidden = false;
    }

    btn.addEventListener('click', function () {
        var f = input.files && input.files[0];
        if (!f) { show(labels.nofile, false); return; }

        var fd = new FormData();
        fd.append('_csrf', btn.getAttribute('data-csrf'));
        fd.append('document', f);

        var originalLabel = btn.textContent;
        btn.disabled = true;
        btn.textContent = btn.getAttribute('data-loading');
        show('…', true);
        window.SysRevAI && window.SysRevAI.showAiOverlay && window.SysRevAI.showAiOverlay();

        fetch(btn.getAttribute('data-url'), { method: 'POST', body: fd })
            .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
            .then(function (res) {
                if (!res.body || !res.body.ok) {
                    var err = (res.body && res.body.error) || 'failed';
                    if (err === 'too_large') show(labels.toobig, false);
                    else if (err === 'unsupported_format') show(labels.format, false);
                    else if (err === 'empty_or_unreadable') show(labels.empty, false);
                    else show(labels.failed + ' (' + err + ')', false);
                    return;
                }
                var d = res.body.data || {};
                // New shape: {primary, secondaries}. Old shape: flat 8
                // fields — applyPrimary() handles both.
                var primary = d.primary ? d.primary : d;
                var secondaries = Array.isArray(d.secondaries) ? d.secondaries : [];
                applyPrimary(primary);
                renderSecondaries(secondaries);
                show(secondaries.length > 0
                        ? labels.okMulti.replace('%d', String(secondaries.length))
                        : labels.ok,
                     true);
            })
            .catch(function () { show(labels.failed, false); })
            .then(function () {
                btn.disabled = false; btn.textContent = originalLabel;
                window.SysRevAI && window.SysRevAI.hideAiOverlay && window.SysRevAI.hideAiOverlay();
            });
    });
})();
</script>
