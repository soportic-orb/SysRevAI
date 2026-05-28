<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Services\RiskOfBiasService;

/** @var array $review */
/** @var array $reference */
/** @var string $tool */
/** @var array $toolDef */
/** @var string[] $enabledTools */
/** @var array $judgements */
$id = (int) $review['id'];
$refId = (int) $reference['id'];
$authors = json_decode((string) $reference['authors_json'], true) ?: [];
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/risk-of-bias"><?= e(__('rob.title')) ?></a> /</div>
        <h1 class="page__title"><?= e((string) ($reference['title'] ?: '—')) ?></h1>
        <p class="muted">
            <?= e(implode('; ', array_slice($authors, 0, 4))) ?>
            <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
        </p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <form method="get" action="/reviews/<?= $id ?>/risk-of-bias/<?= $refId ?>" class="section-card section-card--inline">
        <label class="field-label" style="margin:0"><?= e(__('rob.tool')) ?>:</label>
        <select class="select select--sm" name="tool" onchange="this.form.submit()">
            <?php foreach ($enabledTools as $t): ?>
                <option value="<?= e($t) ?>" <?= $t === $tool ? 'selected' : '' ?>><?= e(__('rob.tool_' . $t)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="/reviews/<?= $id ?>/risk-of-bias/<?= $refId ?>" class="section-card form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="tool" value="<?= e($tool) ?>">

        <?php foreach ($toolDef['domains'] as $domain): $row = $judgements[$domain] ?? null; ?>
            <div class="rob-domain" data-domain="<?= e($domain) ?>">
                <h3 class="rob-domain__title"><?= e(__('rob.d_' . $domain)) ?></h3>
                <div class="form-row form-row--split">
                    <div class="field">
                        <label class="field-label"><?= e(__('rob.judgement')) ?></label>
                        <select class="select" name="judgement[<?= e($domain) ?>]" data-judgement>
                            <option value=""><?= e(__('rob.j_no_information')) ?></option>
                            <?php foreach ($toolDef['judgements'] as $j): ?>
                                <option value="<?= e($j) ?>"
                                        data-color="<?= e(RiskOfBiasService::color($j)) ?>"
                                        <?= (($row['judgement'] ?? null) === $j) ? 'selected' : '' ?>>
                                    <?= e(__('rob.j_' . $j)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label"><?= e(__('rob.justification')) ?></label>
                        <textarea class="input" name="justification[<?= e($domain) ?>]" rows="2"
                                  data-justification><?= e((string) ($row['justification'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="rob-domain__actions">
                    <button type="button" class="btn btn--ghost btn--sm" data-ai-btn
                            data-domain="<?= e($domain) ?>"
                            data-url="/reviews/<?= $id ?>/risk-of-bias/<?= $refId ?>/ai"
                            data-tool="<?= e($tool) ?>"
                            data-loading="<?= e(__('screening.suggest_loading')) ?>"
                            data-error="<?= e(__('screening.ai_error')) ?>">
                        &#10024; <?= e(__('rob.suggest_ai')) ?>
                    </button>
                    <?php if (!empty($row['ai_suggested'])): ?>
                        <span class="muted"><?= e(__('rob.was_ai')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
    </form>
</div>

<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;
    document.querySelectorAll('[data-ai-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.rob-domain');
            var jSel = row.querySelector('[data-judgement]');
            var jTxt = row.querySelector('[data-justification]');
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = btn.getAttribute('data-loading');
            window.SysRevAI && window.SysRevAI.showAiOverlay && window.SysRevAI.showAiOverlay();

            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('tool', btn.getAttribute('data-tool'));
            fd.append('domain', btn.getAttribute('data-domain'));

            fetch(btn.getAttribute('data-url'), { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        if (d.judgement) { jSel.value = d.judgement; }
                        if (d.justification) { jTxt.value = d.justification; }
                    } else {
                        alert(btn.getAttribute('data-error'));
                    }
                })
                .catch(function () { alert(btn.getAttribute('data-error')); })
                .finally(function () {
                    btn.disabled = false; btn.textContent = original;
                    window.SysRevAI && window.SysRevAI.hideAiOverlay && window.SysRevAI.hideAiOverlay();
                });
        });
    });
})();
</script>
