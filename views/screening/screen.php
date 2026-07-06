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
/** @var bool $hasPrev */
/** @var bool $hasNext */
/** @var ?array $ownDecision Reviewer's own past decision on $reference, when reopened from the history list. */
$id = (int) $review['id'];
$total = $completed + $pending;
$pct = $total > 0 ? (int) round($completed / $total * 100) : 100;

// The article card always renders (even with no reference yet) so it has
// stable element ids the decide() AJAX handler can update in place without
// a page navigation — that's what was silently exiting the browser's
// Fullscreen API mode after every screening decision.
$ref = $reference ?? ['id' => 0, 'title' => '', 'year' => null, 'journal' => '', 'doi' => '', 'pmid' => '', 'abstract' => ''];
$authors = $reference ? (json_decode((string) $reference['authors_json'], true) ?: []) : [];

// Cards above the progress bar — all percentages are relative to the
// review's total references (the denominator the user is most likely
// thinking in).
$denom = max(1, (int) ($totalReferences ?? 0));
$pctOf = static function (int $n) use ($denom): int {
    return (int) round(($n / $denom) * 100);
};
?>
<div class="page"
     data-screen-url="/reviews/<?= $id ?>/screen"
     data-screen-can-coordinate="<?= $canCoordinate ? '1' : '0' ?>"
     data-tpl-conflicts="<?= e(__('screening.conflicts')) ?>"
     data-tpl-editing="<?= e(__('screening.editing_previous')) ?>"
     data-dec-include="<?= e(__('screening.include')) ?>"
     data-dec-maybe="<?= e(__('screening.maybe')) ?>"
     data-dec-exclude="<?= e(__('screening.exclude')) ?>"
     data-nav-prev-none="<?= e(__('screening.nav_prev_none')) ?>"
     data-nav-next-none="<?= e(__('screening.nav_next_none')) ?>"
     data-no-abstract="<?= e(__('screening.no_abstract')) ?>"
     data-err-quota="<?= e(__('screening.quota_reached')) ?>"
     data-err-coord="<?= e(__('screening.coord_no_screen')) ?>"
     data-err-nav="<?= e(__('screening.nav_error')) ?>">
    <div class="page__head">
        <div class="screen-head-row">
            <h1 class="page__title">
                <?= e(__('screening.title')) ?>
                <?php $phaseKey = 'screening'; require config('paths.base') . '/views/partials/phase_info.php'; ?>
            </h1>
            <div class="screen-stats screen-stats--compact" aria-label="<?= e(__('screening.stats_aria')) ?>">
                <div class="screen-stat">
                    <span class="screen-stat__value" id="screenStatPendingValue"><?= (int) $pending ?></span>
                    <span class="screen-stat__label"><?= e(__('screening.stat_pending')) ?></span>
                    <span class="screen-stat__pct" id="screenStatPendingPct"><?= $pctOf((int) $pending) ?>% <?= e(__('screening.stat_of_total')) ?></span>
                </div>
                <a class="screen-stat screen-stat--done screen-stat--clickable"
                   href="/reviews/<?= $id ?>/screen/history" title="<?= e(__('screening.history_title')) ?>">
                    <span class="screen-stat__value" id="screenStatDoneValue"><?= (int) $completed ?></span>
                    <span class="screen-stat__label"><?= e(__('screening.stat_done')) ?></span>
                    <span class="screen-stat__pct" id="screenStatDonePct"><?= $pctOf((int) $completed) ?>% <?= e(__('screening.stat_of_total')) ?></span>
                </a>
                <div class="screen-stat screen-stat--team">
                    <span class="screen-stat__value" id="screenStatTeamValue"><?= (int) $totalInStage ?></span>
                    <span class="screen-stat__label"><?= e(__('screening.stat_team_pending')) ?></span>
                    <span class="screen-stat__pct" id="screenStatTeamPct"><?= $pctOf((int) $totalInStage) ?>% <?= e(__('screening.stat_of_total')) ?></span>
                </div>
                <div class="screen-stat screen-stat--total">
                    <span class="screen-stat__value" id="screenStatTotalValue"><?= (int) $totalReferences ?></span>
                    <span class="screen-stat__label"><?= e(__('screening.stat_total_review')) ?></span>
                    <span class="screen-stat__pct">100%</span>
                </div>
            </div>
        </div>
        <div class="screen-head-actions">
            <a class="btn btn--ghost btn--sm" id="screenConflictsLink" href="/reviews/<?= $id ?>/screen/conflicts"
               <?= ($canCoordinate && $conflicts > 0) ? '' : 'hidden' ?>><?= e(__('screening.conflicts', $conflicts)) ?></a>
            <?php if ($canCoordinate): ?>
                <form method="post" action="/reviews/<?= $id ?>/screen/coordinator">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost btn--sm"><?= e(__('screening.coordinator_view')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="screen-progress">
        <div class="progress"><div class="progress__bar" id="screenProgressBar" style="width: <?= $pct ?>%"></div></div>
        <div class="screen-nav">
            <button type="button" class="btn btn--ghost btn--sm" id="screenPrevBtn"
                    title="<?= e(__('screening.nav_prev')) ?>" aria-label="<?= e(__('screening.nav_prev')) ?>"
                    <?= $hasPrev ? '' : 'disabled' ?>>&larr; <?= e(__('screening.nav_prev')) ?></button>
            <button type="button" class="btn btn--ghost btn--sm" id="screenNextBtn"
                    title="<?= e(__('screening.nav_next')) ?>" aria-label="<?= e(__('screening.nav_next')) ?>"
                    <?= $hasNext ? '' : 'disabled' ?>><?= e(__('screening.nav_next')) ?> &rarr;</button>
        </div>
    </div>

    <div class="empty-state" id="screenEmptyState" <?= $reference !== null ? 'hidden' : '' ?>>
        <p><?= e(__('screening.all_done')) ?></p>
        <?php if ($canCoordinate): ?>
            <form method="post" action="/reviews/<?= $id ?>/screen/start" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn--ghost"><?= e(__('screening.start')) ?></button>
            </form>
        <?php endif; ?>
        <a class="btn btn--primary" href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a>
    </div>

    <div class="screen-3col" id="screenActive" <?= $reference === null ? 'hidden' : '' ?>>

        <!-- ── 1/4 — Guide + Protocol, stacked in one column ─────── -->
        <div class="screen-3col__left">
            <?php if (!empty($review['screening_guide'])): ?>
                <!-- ── Screening guide (collapsed by default) ──────── -->
                <aside class="section-card collapse-card screen-guide-card"
                       data-collapsible data-collapsed-default>
                    <button type="button" class="collapse-card__head"
                            data-collapsible-toggle aria-controls="screenGuideBody" aria-expanded="false">
                        <span class="collapse-card__title">
                            &#128218; <?= e(__('reviews.screening_guide')) ?>
                        </span>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="icon icon--chevron" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="collapse-card__body screen-guide__body"
                         id="screenGuideBody" data-collapsible-body hidden>
                        <div class="screen-guide__text"><?= nl2br(e((string) $review['screening_guide'])) ?></div>
                    </div>
                </aside>
            <?php endif; ?>

            <!-- ── Protocol (collapsed by default) ─────────────────── -->
            <aside class="section-card collapse-card"
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
        </div>

        <!-- ── 2/4 — Article + metadata ──────────────────────────── -->
        <section class="screen-3col__article screen-card">
            <h2 class="screen-card__title" id="screenArticleTitle"><?= e((string) ($ref['title'] ?: '—')) ?></h2>
            <p class="muted screen-card__meta" id="screenArticleMeta">
                <?= e(implode('; ', array_slice($authors, 0, 6))) ?><?= count($authors) > 6 ? ' et al.' : '' ?>
                <?php if (!empty($ref['year'])): ?> · <?= (int) $ref['year'] ?><?php endif; ?>
                <?php if (!empty($ref['journal'])): ?> · <em><?= e((string) $ref['journal']) ?></em><?php endif; ?>
            </p>
            <p class="muted screen-card__meta" id="screenArticleIdsRow" <?= (!empty($ref['doi']) || !empty($ref['pmid'])) ? '' : 'hidden' ?>>
                <?php if (!empty($ref['doi'])): ?>DOI: <?= e((string) $ref['doi']) ?><?php endif; ?>
                <?php if (!empty($ref['pmid'])): ?> · PMID: <?= e((string) $ref['pmid']) ?><?php endif; ?>
            </p>
            <div class="screen-card__abstract" id="screenArticleAbstract">
                <?= $ref['abstract'] ? nl2br(e((string) $ref['abstract'])) : '<span class="muted">' . e(__('screening.no_abstract')) . '</span>' ?>
            </div>
        </section>

        <!-- ── 1/4 — Assessment (AI + decision) ─────────────────── -->
        <aside class="screen-3col__assessment section-card">
            <h3 class="section__subtitle"><?= e(__('screening.assessment_title')) ?></h3>

            <!-- Reopened from the "referències revisades" history, or via
                 the prev/next nav arrows onto an already-decided
                 reference — shows what was decided and lets the reviewer
                 change it; the reason/notes fields are pre-filled from
                 this same row. Text and visibility both update via JS on
                 every AJAX-driven nav/decide, since which reference is
                 "own" can change with each one. -->
            <div class="alert alert--warn history-edit-banner" id="screenEditBanner" data-no-toast
                 <?= $ownDecision !== null ? '' : 'hidden' ?>>
                <span id="screenEditBannerText">
                    <?php if ($ownDecision !== null): ?>
                        <?= e(sprintf(
                            __('screening.editing_previous'),
                            __('screening.' . $ownDecision['decision'])
                        )) ?>
                    <?php endif; ?>
                </span>
                <a href="/reviews/<?= $id ?>/screen/history"><?= e(__('screening.history_back')) ?></a>
            </div>

            <div class="ai-suggest">
                <button type="button" class="btn btn--ghost btn--sm btn--block" id="suggestBtn"
                        data-url="/reviews/<?= $id ?>/screen/suggest?reference_id=<?= (int) $ref['id'] ?>"
                        data-loading="<?= e(__('common.working')) ?>"
                        data-error="<?= e(__('screening.ai_error')) ?>">
                    &#10024; <?= e(__('screening.suggest_ai')) ?>
                </button>
                <div class="ai-suggest__panel" id="suggestPanel" hidden></div>
            </div>

            <form method="post" action="/reviews/<?= $id ?>/screen/decide" class="screen-actions" id="screenForm">
                <?= csrf_field() ?>
                <input type="hidden" name="reference_id" id="screenReferenceId" value="<?= (int) $ref['id'] ?>">
                <input type="hidden" name="time_spent" id="timeSpent" value="0">
                <input type="hidden" name="ai_suggestion_json" id="aiSuggestionJson" value="">
                <!-- Mirrors the clicked decision button's value: a script-triggered
                     form.submit() (the no-JS/network-failure fallback) never
                     includes a submit button's name/value, only real inputs. -->
                <input type="hidden" name="decision" id="decisionFallback" value="">

                <?php $ownReason = (string) ($ownDecision['reason'] ?? ''); ?>
                <div class="field">
                    <label class="field-label" for="reason"><?= e(__('screening.exclude_reason')) ?></label>
                    <select class="select" name="reason" id="reason">
                        <option value="" <?= $ownReason === '' ? 'selected' : '' ?>><?= e(__('screening.no_reason')) ?></option>
                        <?php foreach ($reasons as $r): ?>
                            <option value="<?= e((string) $r['label']) ?>" <?= $ownReason === (string) $r['label'] ? 'selected' : '' ?>><?= e((string) $r['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label" for="notes"><?= e(__('screening.notes_label')) ?></label>
                    <textarea class="input" id="notes" name="notes" rows="3" placeholder="<?= e(__('screening.notes')) ?>"><?= e((string) ($ownDecision['notes'] ?? '')) ?></textarea>
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
</div>

<script>
/* Copilot context — lets the floating chat answer questions about the
   article the reviewer is currently looking at. */
window.SysRevAICopilotContext = {
    page:               'titleabstract-screening',
    reference_id:        <?= (int) $ref['id'] ?>,
    reference_title:    <?= json_encode((string) ($ref['title'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_year:      <?= (int) ($ref['year'] ?? 0) ?>,
    reference_journal:  <?= json_encode((string) ($ref['journal'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_doi:      <?= json_encode((string) ($ref['doi'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_pmid:     <?= json_encode((string) ($ref['pmid'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_abstract: <?= json_encode((string) ($ref['abstract'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    has_full_text:      false
};
</script>
<script>
(function () {
    var page = document.querySelector('.page[data-screen-url]');
    if (!page) return;

    var screenUrl   = page.getAttribute('data-screen-url');
    var canCoord    = page.getAttribute('data-screen-can-coordinate') === '1';
    var tplConflict = page.getAttribute('data-tpl-conflicts');
    var tplEditing  = page.getAttribute('data-tpl-editing');
    var noAbstract  = page.getAttribute('data-no-abstract');
    var navNoneMsg  = {
        prev: page.getAttribute('data-nav-prev-none'),
        next: page.getAttribute('data-nav-next-none')
    };
    var decisionLabels = {
        include: page.getAttribute('data-dec-include'),
        maybe:   page.getAttribute('data-dec-maybe'),
        exclude: page.getAttribute('data-dec-exclude')
    };
    var errByKey    = {
        quota_reached:    page.getAttribute('data-err-quota'),
        coord_no_screen:  page.getAttribute('data-err-coord'),
        network:          page.getAttribute('data-err-nav')
    };

    var form        = document.getElementById('screenForm');
    var emptyState  = document.getElementById('screenEmptyState');
    var active      = document.getElementById('screenActive');
    var start       = Date.now();

    /* Open the page already scrolled past the title/stats/progress block,
       so the reviewer lands straight on the screening workspace (guide,
       article, decision). Decisions are submitted via fetch() with no
       navigation, so once set this position holds across references on
       its own — unless the reviewer scrolls manually. */
    (function scrollToWorkspace() {
        if ('scrollRestoration' in history) {
            try { history.scrollRestoration = 'manual'; } catch (e) {}
        }
        var anchor = (active && !active.hidden) ? active : emptyState;
        if (!anchor) return;
        var topbar = document.querySelector('.topbar');
        var subnav = document.querySelector('.review-subnav');
        var offset = (topbar ? topbar.getBoundingClientRect().height : 0)
                   + (subnav ? subnav.getBoundingClientRect().height : 0);
        var target = Math.max(0, anchor.getBoundingClientRect().top + window.pageYOffset - offset);
        window.scrollTo(0, target);
    })();

    function fillTemplate(tpl, n) {
        return (tpl || '').replace('%d', String(n)).replace('%s', String(n));
    }

    function pctOf(n, total) {
        var denom = Math.max(1, total);
        return Math.round((n / denom) * 100);
    }

    function setMultiline(el, text) {
        el.textContent = '';
        if (!text) {
            var span = document.createElement('span');
            span.className = 'muted';
            span.textContent = noAbstract;
            el.appendChild(span);
            return;
        }
        var lines = String(text).split('\n');
        lines.forEach(function (line, i) {
            if (i > 0) el.appendChild(document.createElement('br'));
            el.appendChild(document.createTextNode(line));
        });
    }

    function setMeta(el, authors, year, journal) {
        el.textContent = '';
        el.appendChild(document.createTextNode(authors || ''));
        if (year) el.appendChild(document.createTextNode(' · ' + year));
        if (journal) {
            el.appendChild(document.createTextNode(' · '));
            var em = document.createElement('em');
            em.textContent = journal;
            el.appendChild(em);
        }
    }

    function showError(key) {
        var msg = errByKey[key];
        if (!msg) return;
        window.SysRevAI && window.SysRevAI.toast && window.SysRevAI.toast(msg, 'error');
    }

    /** Apply the JSON state returned by screen()/decide()/nav() in place — no navigation. */
    function applyState(state) {
        var total = state.totalReferences || 0;

        var prevBtn = document.getElementById('screenPrevBtn');
        var nextBtn = document.getElementById('screenNextBtn');
        if (prevBtn) prevBtn.disabled = !state.hasPrev;
        if (nextBtn) nextBtn.disabled = !state.hasNext;

        var pendingEl = document.getElementById('screenStatPendingValue');
        var pendingPctEl = document.getElementById('screenStatPendingPct');
        var doneEl = document.getElementById('screenStatDoneValue');
        var donePctEl = document.getElementById('screenStatDonePct');
        var teamEl = document.getElementById('screenStatTeamValue');
        var teamPctEl = document.getElementById('screenStatTeamPct');
        var totalEl = document.getElementById('screenStatTotalValue');
        if (pendingEl) pendingEl.textContent = state.pending;
        if (pendingPctEl) pendingPctEl.textContent = pctOf(state.pending, total) + '%';
        if (doneEl) doneEl.textContent = state.completed;
        if (donePctEl) donePctEl.textContent = pctOf(state.completed, total) + '%';
        if (teamEl) teamEl.textContent = state.totalInStage;
        if (teamPctEl) teamPctEl.textContent = pctOf(state.totalInStage, total) + '%';
        if (totalEl) totalEl.textContent = total;

        var bar = document.getElementById('screenProgressBar');
        var doneTotal = state.completed + state.pending;
        var pct = doneTotal > 0 ? Math.round((state.completed / doneTotal) * 100) : 100;
        if (bar) bar.style.width = pct + '%';

        var conflictsLink = document.getElementById('screenConflictsLink');
        if (conflictsLink) {
            if (canCoord && state.conflicts > 0) {
                conflictsLink.textContent = fillTemplate(tplConflict, state.conflicts);
                conflictsLink.hidden = false;
            } else {
                conflictsLink.hidden = true;
            }
        }

        var ref = state.reference;
        if (!ref) {
            if (active) active.hidden = true;
            if (emptyState) emptyState.hidden = false;
            return;
        }
        if (emptyState) emptyState.hidden = true;
        if (active) active.hidden = false;

        var authors = ref.authors || '';
        document.getElementById('screenArticleTitle').textContent = ref.title || '—';
        setMeta(document.getElementById('screenArticleMeta'), authors, ref.year, ref.journal);

        var idsRow = document.getElementById('screenArticleIdsRow');
        var idsParts = [];
        if (ref.doi) idsParts.push('DOI: ' + ref.doi);
        if (ref.pmid) idsParts.push('PMID: ' + ref.pmid);
        idsRow.textContent = idsParts.join(' · ');
        idsRow.hidden = idsParts.length === 0;

        setMultiline(document.getElementById('screenArticleAbstract'), ref.abstract);

        document.getElementById('screenReferenceId').value = ref.id;
        document.getElementById('timeSpent').value = 0;
        document.getElementById('aiSuggestionJson').value = '';

        // Reopening (via nav or history) a reference this reviewer already
        // decided pre-fills the form with that decision instead of a blank
        // one, and shows the same banner the server renders on first load.
        var own = state.ownDecision || null;
        document.getElementById('reason').value = own ? (own.reason || '') : '';
        document.getElementById('notes').value = own ? (own.notes || '') : '';
        var editBanner = document.getElementById('screenEditBanner');
        var editBannerText = document.getElementById('screenEditBannerText');
        if (own && editBannerText) {
            editBannerText.textContent = (tplEditing || '').replace('%s', decisionLabels[own.decision] || own.decision);
        }
        if (editBanner) editBanner.hidden = !own;

        start = Date.now();

        var sBtn = document.getElementById('suggestBtn');
        var sPanel = document.getElementById('suggestPanel');
        if (sBtn) sBtn.setAttribute('data-url', screenUrl + '/suggest?reference_id=' + ref.id);
        if (sPanel) { sPanel.hidden = true; sPanel.textContent = ''; sPanel.className = 'ai-suggest__panel'; }

        window.SysRevAICopilotContext = {
            page:               'titleabstract-screening',
            reference_id:        ref.id,
            reference_title:    ref.title || '',
            reference_year:      ref.year || 0,
            reference_journal:  ref.journal || '',
            reference_doi:      ref.doi || '',
            reference_pmid:     ref.pmid || '',
            reference_abstract: ref.abstract || '',
            has_full_text:      false
        };
    }

    function authorsToString(authorsJson) {
        try {
            var list = JSON.parse(authorsJson || '[]') || [];
            var shown = list.slice(0, 6).join('; ');
            return list.length > 6 ? shown + ' et al.' : shown;
        } catch (e) {
            return '';
        }
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById('timeSpent').value = Math.round((Date.now() - start) / 1000);

            var submitter = e.submitter || (document.activeElement && document.activeElement.name === 'decision' ? document.activeElement : null);
            var decision = submitter ? submitter.value : '';
            var fd = new FormData(form);
            if (decision) fd.set('decision', decision);
            document.getElementById('decisionFallback').value = decision;

            fetch(screenUrl + '/decide', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'fetch' }
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        if (d.reference) {
                            d.reference.authors = authorsToString(d.reference.authors_json);
                        }
                        applyState(d);
                    } else {
                        showError(d && d.error);
                    }
                })
                .catch(function () {
                    // Network/parse failure: fall back to a normal submit so the
                    // decision isn't silently lost (this will exit fullscreen,
                    // but only on the rare path where fetch itself failed).
                    form.submit();
                });
        });
    }

    // Prev/next nav arrows — previous steps back through anything already
    // screened (regardless of status), next skips ahead to whatever is
    // still pending, both without leaving the page.
    function navigate(direction) {
        var currentId = document.getElementById('screenReferenceId').value;
        if (!currentId) return;
        fetch(screenUrl + '/nav?direction=' + direction + '&reference_id=' + currentId, {
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    if (d.reference) d.reference.authors = authorsToString(d.reference.authors_json);
                    applyState(d);
                } else {
                    var msg = navNoneMsg[direction];
                    if (msg) window.SysRevAI && window.SysRevAI.toast && window.SysRevAI.toast(msg, 'warn');
                }
            })
            .catch(function () { showError('network'); });
    }
    var prevBtn = document.getElementById('screenPrevBtn');
    var nextBtn = document.getElementById('screenNextBtn');
    if (prevBtn) prevBtn.addEventListener('click', function () { navigate('prev'); });
    if (nextBtn) nextBtn.addEventListener('click', function () { navigate('next'); });

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input,select,textarea')) return;
        if (!active || active.hidden) return;
        var map = { i: 'include', e: 'exclude', m: 'maybe' };
        var dec = map[e.key.toLowerCase()];
        if (dec) {
            var btn = form.querySelector('button[value="' + dec + '"]');
            if (btn) { btn.click(); }
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
