<?php

declare(strict_types=1);

use SysRevAI\Core\Config;
use SysRevAI\Services\FullTextRetrieval\FullTextRetrievalService;

$known = array_keys(FullTextRetrievalService::SOURCE_MAP);
$priority = FullTextRetrievalService::priorityOrder();

$enabled       = (bool) (setting('fulltext.enabled') ?? false);
$mode          = (string) (setting('fulltext.mode') ?? 'manual');
$concurrency   = (int) (setting('fulltext.concurrency') ?? 3);
$timeout       = (int) (setting('fulltext.timeout_seconds') ?? 60);
$retryDays     = (int) (setting('fulltext.retry_after_days') ?? 30);
$exhaustive    = (bool) (setting('fulltext.exhaustive') ?? false);
$downloadNow   = (bool) (setting('fulltext.download_immediately') ?? true);
$politeEmail   = (string) (setting('fulltext.polite_email') ?? '');

$sourceLabel = [
    'unpaywall'        => 'Unpaywall',
    'europepmc'        => 'Europe PMC',
    'pmc'              => 'PMC (NCBI)',
    'openalex'         => 'OpenAlex',
    'semantic_scholar' => 'Semantic Scholar',
    'crossref'         => 'CrossRef',
    'doaj'             => 'DOAJ',
    'arxiv'            => 'arXiv',
    'biorxiv'          => 'bioRxiv / medRxiv',
];

// Short explanation + direct URL where the user can obtain credentials or
// read the official docs. The text keys live under admin.apis.* (shared
// with the "Other APIs" panel) for the five overlapping sources, and under
// admin.fulltext.* for the four that only appear here.
$sourceHelp = [
    'unpaywall'        => ['key' => 'admin.apis.help_unpaywall',         'link_key' => 'admin.apis.link_unpaywall',         'url' => 'https://unpaywall.org/products/api'],
    'europepmc'        => ['key' => 'admin.fulltext.help_europepmc',     'link_key' => 'admin.fulltext.link_europepmc',     'url' => 'https://europepmc.org/RestfulWebService'],
    'pmc'              => ['key' => 'admin.apis.help_pubmed',            'link_key' => 'admin.apis.link_pubmed',            'url' => 'https://www.ncbi.nlm.nih.gov/account/settings/'],
    'openalex'         => ['key' => 'admin.apis.help_openalex',          'link_key' => 'admin.apis.link_openalex',          'url' => 'https://docs.openalex.org/how-to-use-the-api/rate-limits-and-authentication'],
    'semantic_scholar' => ['key' => 'admin.apis.help_semantic_scholar',  'link_key' => 'admin.apis.link_semantic_scholar',  'url' => 'https://www.semanticscholar.org/product/api#api-key-form'],
    'crossref'         => ['key' => 'admin.apis.help_crossref',          'link_key' => 'admin.apis.link_crossref',          'url' => 'https://www.crossref.org/documentation/retrieve-metadata/rest-api/tips-for-using-the-crossref-rest-api/'],
    'doaj'             => ['key' => 'admin.fulltext.help_doaj',          'link_key' => 'admin.fulltext.link_doaj',          'url' => 'https://doaj.org/api/v3/docs'],
    'arxiv'            => ['key' => 'admin.fulltext.help_arxiv',         'link_key' => 'admin.fulltext.link_arxiv',         'url' => 'https://info.arxiv.org/help/api/index.html'],
    'biorxiv'          => ['key' => 'admin.fulltext.help_biorxiv',       'link_key' => 'admin.fulltext.link_biorxiv',       'url' => 'https://api.biorxiv.org/'],
];
?>
<h1 class="section__title"><?= e(__('admin.sections.fulltext')) ?></h1>
<p class="section__intro"><?= e(__('admin.fulltext.intro')) ?></p>

<form method="post" action="/admin/settings/fulltext" class="form-grid section-card">
    <?= csrf_field() ?>

    <label class="checkbox">
        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
        <?= e(__('admin.fulltext.enabled')) ?>
    </label>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="mode"><?= e(__('admin.fulltext.mode')) ?></label>
            <select class="select" id="mode" name="mode">
                <?php foreach (['manual', 'on_import', 'scheduled'] as $m): ?>
                    <option value="<?= $m ?>" <?= $mode === $m ? 'selected' : '' ?>><?= e(__('admin.fulltext.mode_' . $m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--narrow">
            <label class="field-label" for="concurrency"><?= e(__('admin.fulltext.concurrency')) ?></label>
            <input class="input" id="concurrency" name="concurrency" type="number" min="1" max="10" value="<?= $concurrency ?>">
        </div>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="timeout_seconds"><?= e(__('admin.fulltext.timeout')) ?></label>
            <input class="input" id="timeout_seconds" name="timeout_seconds" type="number" min="5" value="<?= $timeout ?>">
        </div>
        <div class="field">
            <label class="field-label" for="retry_after_days"><?= e(__('admin.fulltext.retry_after')) ?></label>
            <input class="input" id="retry_after_days" name="retry_after_days" type="number" min="1" value="<?= $retryDays ?>">
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="polite_email"><?= e(__('admin.fulltext.polite_email')) ?></label>
        <input class="input" id="polite_email" name="polite_email" type="email" value="<?= e($politeEmail) ?>">
        <span class="field-help"><?= e(__('admin.fulltext.polite_email_help')) ?></span>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="exhaustive" value="1" <?= $exhaustive ? 'checked' : '' ?>>
        <?= e(__('admin.fulltext.exhaustive')) ?>
    </label>

    <label class="checkbox">
        <input type="checkbox" name="download_immediately" value="1" <?= $downloadNow ? 'checked' : '' ?>>
        <?= e(__('admin.fulltext.download_immediately')) ?>
    </label>

    <div class="field">
        <label class="field-label" for="priority"><?= e(__('admin.fulltext.priority')) ?></label>
        <textarea class="input" id="priority" name="priority" rows="<?= count($known) ?>"><?= e(implode("\n", $priority)) ?></textarea>
        <span class="field-help"><?= e(__('admin.fulltext.priority_help')) ?></span>
    </div>

    <h2 class="section__subtitle" style="margin-top:18px"><?= e(__('admin.fulltext.sources')) ?></h2>

    <?php foreach ($priority as $name): if (!in_array($name, $known, true)) continue; ?>
        <?php
            $sEnabled = (bool) (setting('fulltext.' . $name . '.enabled') ?? false);
            $sEmail   = (string) (setting('fulltext.' . $name . '.email') ?? '');
            $hasKey   = Config::hasEncrypted('fulltext.' . $name . '.api_key')
                || ($name === 'pmc' && Config::hasEncrypted('pubmed.api_key'));
        ?>
        <div class="api-block ft-source" data-source="<?= e($name) ?>">
            <h3 class="section__subtitle"><?= e($sourceLabel[$name] ?? $name) ?></h3>
            <?php if (isset($sourceHelp[$name])): ?>
                <p class="api-help">
                    <?= e(__($sourceHelp[$name]['key'])) ?>
                    <a href="<?= e($sourceHelp[$name]['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= e(__($sourceHelp[$name]['link_key'])) ?> &rarr;
                    </a>
                </p>
            <?php endif; ?>
            <label class="checkbox">
                <input type="checkbox" name="sources[<?= e($name) ?>][enabled]" value="1" <?= $sEnabled ? 'checked' : '' ?>>
                <?= e(__('admin.fulltext.enable_source')) ?>
            </label>
            <div class="form-row form-row--split" style="margin-top:8px">
                <div class="field">
                    <label class="field-label"><?= e(__('admin.fulltext.source_email')) ?></label>
                    <input class="input" name="sources[<?= e($name) ?>][email]" type="email" value="<?= e($sEmail) ?>">
                </div>
                <?php if (in_array($name, ['pmc', 'semantic_scholar'], true)): ?>
                    <?php
                        $keyLabel = $name === 'pmc' ? __('admin.fulltext.pmc_key') : __('admin.fulltext.s2_key');
                        $hasKeyHere = Config::hasEncrypted('fulltext.' . $name . '.api_key')
                            || ($name === 'pmc' && Config::hasEncrypted('pubmed.api_key'))
                            || ($name === 'semantic_scholar' && Config::hasEncrypted('semantic_scholar.api_key'));
                    ?>
                    <div class="field">
                        <label class="field-label"><?= e($keyLabel) ?></label>
                        <input class="input" name="sources[<?= e($name) ?>][api_key]" type="password" autocomplete="off"
                               placeholder="<?= $hasKeyHere ? '••••••  (' . e(__('admin.claude.key_set')) . ')' : '' ?>">
                    </div>
                <?php endif; ?>
            </div>
            <div class="ft-source__actions">
                <button type="button" class="btn btn--ghost btn--sm" data-verify-source="<?= e($name) ?>">
                    <?= e(__('admin.fulltext.verify')) ?>
                </button>
                <span class="muted ft-source__status" data-status></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.fulltext.test_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.fulltext.test_intro')) ?></p>
    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="test_doi">DOI</label>
            <input class="input" id="test_doi" placeholder="10.1371/journal.pone.0173152">
        </div>
        <div class="field field--narrow">
            <label class="field-label" for="test_pmid">PMID</label>
            <input class="input" id="test_pmid" placeholder="…">
        </div>
    </div>
    <div class="btn-row" style="margin-top:10px">
        <button type="button" class="btn btn--primary" id="ftTestBtn"
                data-url="/admin/settings/fulltext/test"
                data-csrf="<?= e(csrf_token()) ?>"
                data-loading="<?= e(__('admin.fulltext.testing')) ?>">
            <?= e(__('admin.fulltext.test_run')) ?>
        </button>
    </div>
    <div class="ft-test-result" id="ftTestResult" hidden></div>
</div>

<script>
(function () {
    var verifyCsrf = <?= json_encode(csrf_token()) ?>;
    document.querySelectorAll('[data-verify-source]').forEach(function (btn) {
        var name = btn.getAttribute('data-verify-source');
        btn.addEventListener('click', function () {
            var statusEl = btn.parentElement.querySelector('[data-status]');
            statusEl.textContent = '…';
            var fd = new FormData();
            fd.append('_csrf', verifyCsrf);
            fd.append('source', name);
            fetch('/admin/settings/fulltext/verify', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        statusEl.textContent = '✓ ' + (d.message || 'OK');
                        statusEl.style.color = '#06624a';
                    } else {
                        statusEl.textContent = '✗ ' + (d.message || 'error');
                        statusEl.style.color = '#9c3b00';
                    }
                })
                .catch(function () { statusEl.textContent = 'error'; });
        });
    });

    var testBtn = document.getElementById('ftTestBtn');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var doi = document.getElementById('test_doi').value.trim();
            var pmid = document.getElementById('test_pmid').value.trim();
            if (!doi && !pmid) return;
            var out = document.getElementById('ftTestResult');
            out.hidden = false;
            out.textContent = testBtn.getAttribute('data-loading');

            var fd = new FormData();
            fd.append('_csrf', testBtn.getAttribute('data-csrf'));
            fd.append('doi', doi);
            fd.append('pmid', pmid);

            fetch(testBtn.getAttribute('data-url'), { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { out.textContent = 'Error: ' + (d.error || 'unknown'); return; }
                    var lines = ['Result: ' + (d.success ? '✓ found via ' + (d.result && d.result.source) : '✗ no source found'), ''];
                    (d.attempts || []).forEach(function (a) {
                        lines.push('• [' + a.source + '] ' + a.status + ' (' + (a.response_time_ms || 0) + ' ms)'
                            + (a.pdf_url ? '\n    → ' + a.pdf_url : ''));
                    });
                    out.textContent = lines.join('\n');
                })
                .catch(function () { out.textContent = 'request failed'; });
        });
    }
})();
</script>
