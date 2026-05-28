<?php

declare(strict_types=1);

/** @var array $review */
/** @var ?array $reference */
/** @var array $pico */
/** @var array $reasons */
/** @var int $pending */
/** @var int $completed */
/** @var int $totalReferences */
/** @var int $totalInStage */
/** @var int $conflicts */
/** @var bool $canCoordinate */
$id = (int) $review['id'];
$total = $completed + $pending;
$pct = $total > 0 ? (int) round($completed / $total * 100) : 100;
$authors = $reference ? (json_decode((string) $reference['authors_json'], true) ?: []) : [];

// Cards above the progress bar — all percentages are relative to the
// review's total references (the denominator the user is most likely
// thinking in).
$denom = max(1, (int) ($totalReferences ?? 0));
$pctOf = static function (int $n) use ($denom): int {
    return (int) round(($n / $denom) * 100);
};
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
            <h1 class="page__title">
                <?= e(__('screening.title')) ?>
                <?php $phaseKey = 'screening'; require config('paths.base') . '/views/partials/phase_info.php'; ?>
            </h1>
        </div>
        <div class="btn-row">
            <?php if ($canCoordinate && $conflicts > 0): ?>
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/screen/conflicts"><?= e(__('screening.conflicts', $conflicts)) ?></a>
            <?php endif; ?>
            <?php if ($canCoordinate): ?>
                <form method="post" action="/reviews/<?= $id ?>/screen/coordinator">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e(__('screening.coordinator_view')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="screen-stats" aria-label="<?= e(__('screening.stats_aria')) ?>">
        <div class="screen-stat">
            <span class="screen-stat__value"><?= (int) $pending ?></span>
            <span class="screen-stat__label"><?= e(__('screening.stat_pending')) ?></span>
            <span class="screen-stat__pct"><?= $pctOf((int) $pending) ?>% <?= e(__('screening.stat_of_total')) ?></span>
        </div>
        <div class="screen-stat screen-stat--done">
            <span class="screen-stat__value"><?= (int) $completed ?></span>
            <span class="screen-stat__label"><?= e(__('screening.stat_done')) ?></span>
            <span class="screen-stat__pct"><?= $pctOf((int) $completed) ?>% <?= e(__('screening.stat_of_total')) ?></span>
        </div>
        <div class="screen-stat screen-stat--team">
            <span class="screen-stat__value"><?= (int) $totalInStage ?></span>
            <span class="screen-stat__label"><?= e(__('screening.stat_team_pending')) ?></span>
            <span class="screen-stat__pct"><?= $pctOf((int) $totalInStage) ?>% <?= e(__('screening.stat_of_total')) ?></span>
        </div>
        <div class="screen-stat screen-stat--total">
            <span class="screen-stat__value"><?= (int) $totalReferences ?></span>
            <span class="screen-stat__label"><?= e(__('screening.stat_total_review')) ?></span>
            <span class="screen-stat__pct">100%</span>
        </div>
    </div>

    <div class="screen-progress">
        <div class="progress"><div class="progress__bar" style="width: <?= $pct ?>%"></div></div>
        <span class="muted"><?= e(__('screening.pending_you', $pending)) ?> · <?= e(__('screening.done_you', $completed)) ?></span>
    </div>

    <?php if ($reference === null): ?>
        <div class="empty-state">
            <p><?= e(__('screening.all_done')) ?></p>
            <?php if ($canCoordinate): ?>
                <form method="post" action="/reviews/<?= $id ?>/screen/start" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e(__('screening.start')) ?></button>
                </form>
            <?php endif; ?>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a>
        </div>
    <?php else: ?>
        <div class="screen-3col">

            <!-- ── 1/4 — Protocol (collapsed by default) ─────────────── -->
            <aside class="screen-3col__protocol section-card collapse-card"
                   data-collapsible data-collapsed-default>
                <button type="button" class="collapse-card__head"
                        data-collapsible-toggle aria-controls="screenProtocolBody" aria-expanded="false">
                    <span class="collapse-card__title"><?= e(__('reviews.protocol')) ?></span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon--chevron" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="collapse-card__body screen-protocol__body"
                     id="screenProtocolBody" data-collapsible-body hidden>
                    <?php if (!empty($review['question'])): ?>
                        <p><strong><?= e(__('reviews.question')) ?>:</strong><br><?= nl2br(e((string) $review['question'])) ?></p>
                    <?php endif; ?>
                    <?php foreach (['population', 'intervention', 'comparison', 'outcome', 'study_design'] as $f): ?>
                        <?php if (!empty($pico[$f])): ?>
                            <p><strong><?= e(__('reviews.pico_' . $f)) ?>:</strong> <?= e((string) $pico[$f]) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($review['inclusion_criteria'])): ?>
                        <p><strong><?= e(__('reviews.inclusion')) ?>:</strong><br><?= nl2br(e((string) $review['inclusion_criteria'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($review['exclusion_criteria'])): ?>
                        <p><strong><?= e(__('reviews.exclusion')) ?>:</strong><br><?= nl2br(e((string) $review['exclusion_criteria'])) ?></p>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- ── 2/4 — Article + metadata ──────────────────────────── -->
            <section class="screen-3col__article screen-card">
                <h2 class="screen-card__title"><?= e((string) ($reference['title'] ?: '—')) ?></h2>
                <p class="muted screen-card__meta">
                    <?= e(implode('; ', array_slice($authors, 0, 6))) ?><?= count($authors) > 6 ? ' et al.' : '' ?>
                    <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
                    <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
                </p>
                <?php if (!empty($reference['doi']) || !empty($reference['pmid'])): ?>
                    <p class="muted screen-card__meta">
                        <?php if (!empty($reference['doi'])): ?>DOI: <?= e((string) $reference['doi']) ?><?php endif; ?>
                        <?php if (!empty($reference['pmid'])): ?> · PMID: <?= e((string) $reference['pmid']) ?><?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="screen-card__abstract">
                    <?= $reference['abstract'] ? nl2br(e((string) $reference['abstract'])) : '<span class="muted">' . e(__('screening.no_abstract')) . '</span>' ?>
                </div>
            </section>

            <!-- ── 1/4 — Assessment (AI + decision) ─────────────────── -->
            <aside class="screen-3col__assessment section-card">
                <h3 class="section__subtitle"><?= e(__('screening.assessment_title')) ?></h3>

                <div class="ai-suggest">
                    <button type="button" class="btn btn--ghost btn--sm btn--block" id="suggestBtn"
                            data-url="/reviews/<?= $id ?>/screen/suggest?reference_id=<?= (int) $reference['id'] ?>"
                            data-loading="<?= e(__('common.working')) ?>"
                            data-error="<?= e(__('screening.ai_error')) ?>">
                        &#10024; <?= e(__('screening.suggest_ai')) ?>
                    </button>
                    <div class="ai-suggest__panel" id="suggestPanel" hidden></div>
                </div>

                <form method="post" action="/reviews/<?= $id ?>/screen/decide" class="screen-actions" id="screenForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reference_id" value="<?= (int) $reference['id'] ?>">
                    <input type="hidden" name="time_spent" id="timeSpent" value="0">
                    <input type="hidden" name="ai_suggestion_json" id="aiSuggestionJson" value="">

                    <div class="field">
                        <label class="field-label" for="reason"><?= e(__('screening.exclude_reason')) ?></label>
                        <select class="select" name="reason" id="reason">
                            <option value=""><?= e(__('screening.no_reason')) ?></option>
                            <?php foreach ($reasons as $r): ?>
                                <option value="<?= e((string) $r['label']) ?>"><?= e((string) $r['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="field-label" for="notes"><?= e(__('screening.notes_label')) ?></label>
                        <textarea class="input" id="notes" name="notes" rows="3" placeholder="<?= e(__('screening.notes')) ?>"></textarea>
                    </div>

                    <div class="decision-buttons decision-buttons--stack">
                        <button type="submit" name="decision" value="include" class="btn btn--include">
                            <?= e(__('screening.include')) ?> <kbd>I</kbd>
                        </button>
                        <button type="submit" name="decision" value="maybe" class="btn btn--maybe">
                            <?= e(__('screening.maybe')) ?> <kbd>M</kbd>
                        </button>
                        <button type="submit" name="decision" value="exclude" class="btn btn--exclude">
                            <?= e(__('screening.exclude')) ?> <kbd>E</kbd>
                        </button>
                    </div>

                    <p class="muted screen-legend"><?= e(__('screening.shortcuts')) ?></p>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($reference !== null): ?>
<script>
(function () {
    var start = Date.now();
    var form = document.getElementById('screenForm');
    form.addEventListener('submit', function () {
        document.getElementById('timeSpent').value = Math.round((Date.now() - start) / 1000);
    });
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input,select,textarea')) return;
        var map = { i: 'include', e: 'exclude', m: 'maybe' };
        var dec = map[e.key.toLowerCase()];
        if (dec) {
            var btn = form.querySelector('button[value="' + dec + '"]');
            if (btn) { document.getElementById('timeSpent').value = Math.round((Date.now() - start) / 1000); btn.click(); }
        }
    });

    var sBtn = document.getElementById('suggestBtn');
    var sPanel = document.getElementById('suggestPanel');
    var aiInput = document.getElementById('aiSuggestionJson');
    if (sBtn) {
        sBtn.addEventListener('click', function () {
            sPanel.hidden = false;
            sPanel.className = 'ai-suggest__panel';
            sPanel.textContent = sBtn.getAttribute('data-loading');
            window.SysRevAI && window.SysRevAI.showAiOverlay && window.SysRevAI.showAiOverlay();
            fetch(sBtn.getAttribute('data-url'), { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok && d.data) {
                        var rec = String(d.data.recommendation || '').toLowerCase();
                        var conf = d.data.confidence != null ? Math.round(d.data.confidence * 100) + '%' : '';
                        sPanel.className = 'ai-suggest__panel is-' + rec;
                        var reason = d.data.reason ? String(d.data.reason).replace(/[<>&]/g, '') : '';
                        sPanel.innerHTML = '<strong>' + rec.toUpperCase() + '</strong> ' + conf +
                            (reason ? '<br>' + reason : '');
                        // Stash the raw suggestion so it travels with the
                        // decision POST for traceability.
                        var trace = {
                            recommendation: rec,
                            confidence:     d.data.confidence != null ? d.data.confidence : null,
                            reason:         d.data.reason || '',
                            language:       d.language || '',
                            shown_at:       new Date().toISOString()
                        };
                        if (aiInput) aiInput.value = JSON.stringify(trace);
                    } else {
                        sPanel.textContent = sBtn.getAttribute('data-error');
                    }
                })
                .catch(function () { sPanel.textContent = sBtn.getAttribute('data-error'); })
                .finally(function () { window.SysRevAI && window.SysRevAI.hideAiOverlay && window.SysRevAI.hideAiOverlay(); });
        });
    }
})();
</script>
<?php endif; ?>
