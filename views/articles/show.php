<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $article */
/** @var bool  $isOwner */
/** @var array $members */
/** @var array $history */
/** @var array $documents Secondary article documents (from ArticleDocument::forArticle). */

// Suppress the floating Copilot widget — we embed the chat inline here.
$hideCopilotWidget = true;
$id = (int) $article['id'];
$text = (string) ($article['extracted_text'] ?? '');
?>
<div class="page article-page article-page--workspace">
    <div class="page__head article-head">
        <div class="article-head__title">
            <h1 class="page__title"><?= e((string) ($article['title'] ?: '—')) ?></h1>
            <p class="page__subtitle muted">
                <?php if (!empty($article['source_filename'])): ?>
                    <?= e((string) $article['source_filename']) ?>
                <?php endif; ?>
                · <?= e(__('articles.size_chars', (int) ($article['char_count'] ?? 0))) ?>
                · <?= count($members) > 0 ? e(__('articles.team_count', count($members) + 1)) : e(__('articles.solo')) ?>
            </p>
        </div>
        <?php
            $articleActionsActive = 'workspace';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($warn = Session::pullFlash('warning')) !== null): ?>
        <div class="alert alert--warn"><?= e((string) $warn) ?></div>
    <?php endif; ?>

    <div class="article-workspace" id="articleWorkspace"
         data-article-id="<?= $id ?>"
         data-csrf="<?= e(csrf_token()) ?>">
        <!-- Slim re-open button shown only when the text pane is collapsed.
             Sits on the far left as a black tile with a white article icon. -->
        <button type="button" class="article-pane__reopen"
                id="articleTextReopen"
                title="<?= e(__('articles.text_pane_open')) ?>"
                aria-label="<?= e(__('articles.text_pane_open')) ?>"
                aria-controls="articleTextPane"
                aria-expanded="true"
                hidden>
            <?php $iconName = 'abstract'; $iconClass = 'article-pane__reopen-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
        </button>

        <!-- LEFT column wrapper holds the (optional) secondary-documents
             collapsible card stacked above the article text pane so both
             share the same width and the collapse of the article text
             pane still hides only itself. -->
        <div class="article-left-col">

        <?php if (!empty($documents)): ?>
            <!-- Secondary documents the user attached — Word / PDF
                 references the Copilot reads alongside the main paper.
                 Collapsed by default; reuses the platform's collapse-
                 card pattern from the screening boards. -->
            <section class="section-card collapse-card article-docs-card"
                     data-collapsible data-collapsed-default>
                <button type="button" class="collapse-card__head"
                        data-collapsible-toggle aria-controls="articleDocsBody" aria-expanded="false">
                    <span class="collapse-card__title">
                        &#128209; <?= e(__('articles.documents_title')) ?>
                        <span class="muted">(<?= count($documents) ?>)</span>
                    </span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon--chevron" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="collapse-card__body article-docs-card__body"
                     id="articleDocsBody" data-collapsible-body hidden>
                    <ul class="article-docs-list">
                        <?php foreach ($documents as $doc):
                            $docId = (int) $doc['id'];
                            $name  = (string) ($doc['filename'] ?? '—');
                            $chars = (int) ($doc['char_count'] ?? 0);
                        ?>
                            <li class="article-docs-list__item">
                                <a class="article-docs-list__name"
                                   href="/tools/articles/<?= $id ?>/documents/<?= $docId ?>/download"
                                   title="<?= e(__('articles.document_download')) ?>">
                                    <?= e($name) ?>
                                </a>
                                <span class="muted article-docs-list__meta">
                                    <?php if ($chars > 0): ?>
                                        <?= e(__('articles.size_chars', $chars)) ?>
                                    <?php else: ?>
                                        <?= e(__('articles.no_text_extracted_tag')) ?>
                                    <?php endif; ?>
                                </span>
                                <form method="post"
                                      action="/tools/articles/<?= $id ?>/documents/<?= $docId ?>/delete"
                                      class="inline-form"
                                      data-confirm="<?= e(sprintf(__('articles.document_delete_confirm'), $name)) ?>"
                                      data-confirm-tone="danger"
                                      data-confirm-button="<?= e(__('articles.document_delete')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn btn--ghost btn--xs btn--danger"
                                            title="<?= e(__('articles.document_delete')) ?>"
                                            aria-label="<?= e(__('articles.document_delete')) ?>">&times;</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- LEFT: article text — full half-width pane while open; the
             header's collapse button hides it entirely and lets the chat
             pane span the workspace. -->
        <section class="article-pane article-pane--text section-card"
                 id="articleTextPane"
                 aria-label="<?= e(__('articles.text_pane_label')) ?>">
            <div class="article-pane__head">
                <h2 class="section__subtitle"><?= e(__('articles.text_pane_title')) ?></h2>
                <?php if (trim($text) === ''): ?>
                    <span class="tag tag--warn"><?= e(__('articles.no_text_extracted_tag')) ?></span>
                <?php endif; ?>
                <button type="button" class="article-pane__collapse"
                        id="articleTextCollapse"
                        title="<?= e(__('articles.text_pane_close')) ?>"
                        aria-label="<?= e(__('articles.text_pane_close')) ?>"
                        aria-controls="articleTextPane">
                    &times;
                </button>
            </div>
            <div class="article-pane__text">
<?php if (trim($text) !== ''): ?>
<pre class="article-pane__pre"><?= e($text) ?></pre>
<?php else: ?>
                <p class="muted"><?= e(__('articles.no_text_extracted')) ?></p>
<?php endif; ?>
            </div>
        </section>

        </div><!-- /.article-left-col -->

        <!-- RIGHT column: upload-secondary button stacked above the
             chat pane so the user can attach extra material in the
             same vertical rhythm as the chat. -->
        <div class="article-right-col">
            <div class="article-docs-upload">
                <button type="button" class="btn btn--ghost btn--sm article-docs-upload__btn"
                        id="articleDocsUploadBtn">
                    <?php $iconName = 'book_upload'; $iconClass = 'icon-action'; require config('paths.base') . '/views/partials/icon.php'; ?>
                    <span><?= e(__('articles.documents_upload_btn')) ?></span>
                </button>
            </div>

            <!-- RIGHT: chat — same height as the article pane; the messages
                 container scrolls inside, the input row stays pinned. When
                 the text pane collapses, this section spans the full width. -->
            <section class="article-pane article-pane--chat section-card" aria-label="<?= e(__('articles.chat_pane_label')) ?>">
                <?php
                // Pass needed scope to the partial.
                $articleChatId   = $id;
                $articleHistory  = $history;
                require config('paths.base') . '/views/partials/article_chat_panel.php';
                ?>
            </section>
        </div>
    </div>
</div>

<!-- Upload modal: drag-and-drop area + native file input. POSTs to
     /tools/articles/{id}/documents and reloads the workspace so the
     server-rendered collapsible card picks up the new attachment. -->
<dialog class="info-modal article-docs-modal" id="articleDocsModal">
    <div class="info-modal__inner">
        <header class="info-modal__head">
            <h2 class="info-modal__title"><?= e(__('articles.documents_upload_title')) ?></h2>
            <button type="button" class="info-modal__close" id="articleDocsClose"
                    aria-label="<?= e(__('common.cancel')) ?>">&times;</button>
        </header>
        <p class="info-modal__body muted"><?= e(__('articles.documents_upload_intro')) ?></p>
        <div class="article-docs-drop" id="articleDocsDrop">
            <p><?= e(__('articles.documents_drop_hint')) ?></p>
            <input type="file" id="articleDocsInput" name="file"
                   accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                   multiple>
        </div>
        <ul class="article-docs-modal__status" id="articleDocsStatus" hidden></ul>
        <footer class="info-modal__foot article-docs-modal__foot">
            <button type="button" class="btn btn--ghost btn--sm" id="articleDocsCancel">
                <?= e(__('common.cancel')) ?>
            </button>
            <a class="btn btn--primary btn--sm" id="articleDocsDone" href="javascript:location.reload()"
               hidden><?= e(__('articles.documents_done')) ?></a>
        </footer>
    </div>
</dialog>

<script>
(function () {
    'use strict';
    var workspace = document.getElementById('articleWorkspace');
    if (!workspace) return;
    var pane      = document.getElementById('articleTextPane');
    var closeBtn  = document.getElementById('articleTextCollapse');
    var reopenBtn = document.getElementById('articleTextReopen');
    if (!pane || !closeBtn || !reopenBtn) return;

    var key = 'sysrevai.articles.text_collapsed.' + workspace.getAttribute('data-article-id');
    var collapsed = false;
    try { collapsed = localStorage.getItem(key) === '1'; } catch (e) {}
    apply();

    closeBtn.addEventListener('click', function () { collapsed = true; persist(); apply(); reopenBtn.focus(); });
    reopenBtn.addEventListener('click', function () { collapsed = false; persist(); apply(); closeBtn.focus(); });

    function apply() {
        workspace.classList.toggle('is-text-collapsed', collapsed);
        pane.hidden = collapsed;
        reopenBtn.hidden = !collapsed;
        reopenBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    function persist() {
        try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (e) {}
    }
})();
</script>

<script>
/* Secondary-documents upload: opens a modal with a native file input
   + drag-drop area, POSTs each file to /tools/articles/{id}/documents
   and shows per-file status. Reload on Done so the server-rendered
   collapsible card refreshes with the new attachments. */
(function () {
    'use strict';
    var workspace = document.getElementById('articleWorkspace');
    if (!workspace) return;
    var articleId = workspace.getAttribute('data-article-id');
    var csrf      = workspace.getAttribute('data-csrf');
    var openBtn   = document.getElementById('articleDocsUploadBtn');
    var modal     = document.getElementById('articleDocsModal');
    var closeBtn  = document.getElementById('articleDocsClose');
    var cancelBtn = document.getElementById('articleDocsCancel');
    var drop      = document.getElementById('articleDocsDrop');
    var input     = document.getElementById('articleDocsInput');
    var statusEl  = document.getElementById('articleDocsStatus');
    var doneBtn   = document.getElementById('articleDocsDone');
    if (!openBtn || !modal || !drop || !input) return;

    function open() {
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', '');
        statusEl.hidden = true; statusEl.innerHTML = '';
        doneBtn.hidden = true;
    }
    function close() {
        if (typeof modal.close === 'function') modal.close();
        else modal.removeAttribute('open');
    }
    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    cancelBtn.addEventListener('click', close);

    /**
     * Build a per-file row: filename on top, animated progress bar
     * below, status pill on the right. Returns DOM nodes the upload
     * routine can mutate as the XHR progresses (.progress.fill width,
     * .pill swap to check icon, …).
     */
    function makeRow(file) {
        statusEl.hidden = false;
        var li = document.createElement('li');
        li.className = 'article-docs-modal__status-item article-docs-modal__status-item--pending';

        var head = document.createElement('div');
        head.className = 'article-docs-modal__status-row';
        var name = document.createElement('span');
        name.className = 'article-docs-modal__status-name';
        name.textContent = file.name;
        var pill = document.createElement('span');
        pill.className = 'article-docs-modal__status-pill';
        pill.textContent = <?= json_encode(__('articles.documents_uploading')) ?>;
        head.appendChild(name);
        head.appendChild(pill);

        var bar = document.createElement('div');
        bar.className = 'article-docs-modal__progress';
        var fill = document.createElement('span');
        fill.className = 'article-docs-modal__progress-fill';
        bar.appendChild(fill);

        li.appendChild(head);
        li.appendChild(bar);
        statusEl.appendChild(li);
        return { li: li, pill: pill, bar: bar, fill: fill };
    }

    var CHECK_SVG = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" '
        + 'stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<polyline points="20 6 9 17 4 12"></polyline></svg>';

    function uploadOne(file) {
        var row = makeRow(file);
        return new Promise(function (resolve) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/tools/articles/' + articleId + '/documents');
            xhr.setRequestHeader('X-CSRF-Token', csrf);

            // Real upload-progress feedback. While the file is in
            // flight, fill the bar to the byte ratio; once the server
            // takes over (loaded === total), bump to ~95% and let the
            // determinate transition land on 100% on success.
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) return;
                    var pct = Math.round(e.loaded / e.total * 90);
                    row.fill.style.width = pct + '%';
                });
                xhr.upload.addEventListener('load', function () {
                    row.fill.style.width = '95%';
                });
            }
            xhr.onload = function () {
                var body = null;
                try { body = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status === 200 && body && body.ok) {
                    row.fill.style.width = '100%';
                    row.li.classList.remove('article-docs-modal__status-item--pending');
                    row.li.classList.add('article-docs-modal__status-item--ok');
                    row.pill.innerHTML = CHECK_SVG + '<span>'
                        + <?= json_encode(__('articles.documents_uploaded_ok')) ?>
                        + '</span>';
                } else {
                    row.li.classList.remove('article-docs-modal__status-item--pending');
                    row.li.classList.add('article-docs-modal__status-item--error');
                    var err = (body && body.error)
                        || <?= json_encode(__('articles.documents_uploaded_failed')) ?>;
                    row.pill.textContent = err;
                    row.bar.classList.add('is-failed');
                }
                resolve();
            };
            xhr.onerror = function () {
                row.li.classList.remove('article-docs-modal__status-item--pending');
                row.li.classList.add('article-docs-modal__status-item--error');
                row.pill.textContent = <?= json_encode(__('articles.documents_uploaded_failed')) ?>;
                row.bar.classList.add('is-failed');
                resolve();
            };

            var fd = new FormData();
            // CSRF in body too — covers the path inside src/Core/Csrf.php
            // that checks $_POST['_csrf'] before HTTP_X_CSRF_TOKEN.
            fd.append('_csrf', csrf);
            fd.append('file', file, file.name);
            xhr.send(fd);
        });
    }

    function uploadFiles(files) {
        if (!files || files.length === 0) return;
        var queue = Promise.resolve();
        Array.prototype.forEach.call(files, function (f) {
            queue = queue.then(function () { return uploadOne(f); });
        });
        queue.then(function () { doneBtn.hidden = false; });
    }

    input.addEventListener('change', function () { uploadFiles(input.files); });

    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) {
            uploadFiles(e.dataTransfer.files);
        }
    });
})();
</script>
