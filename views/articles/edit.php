<?php

declare(strict_types=1);

/** @var array  $article */
/** @var bool   $isOwner */
/** @var string $initialHtml */
/** @var bool   $hasReport */

$id = (int) $article['id'];

// The floating Copilot widget stays available on this page (per spec);
// the in-page editor talks to its own /edit/copilot endpoint, separate
// from the generic chat used by the floating button.
?>
<div class="page article-editor-page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <?= e((string) ($article['title'] ?: '—')) ?>
                <span class="muted">— <?= e(__('articles.editor.title')) ?></span>
            </h1>
            <p class="page__subtitle muted">
                <?= e(__('articles.editor.subtitle')) ?>
                <span class="article-editor__save-state" id="editorSaveState">·</span>
            </p>
        </div>
        <?php
            $articleActionsActive = 'editor';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <div class="article-editor"
         id="articleEditor"
         data-article-id="<?= $id ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         data-has-report="<?= $hasReport ? '1' : '0' ?>">
        <textarea id="articleEditorTextarea" style="display:none"><?= e($initialHtml) ?></textarea>

        <!-- Bubble button that appears on a non-empty selection. -->
        <button type="button" class="article-editor__bubble"
                id="editorBubble" hidden
                title="<?= e(__('articles.editor.bubble_hint')) ?>">
            &#10024; <?= e(__('articles.editor.bubble_label')) ?>
        </button>

        <!-- Floating panel: prompt input + Accept/Reject preview. -->
        <div class="article-editor__panel" id="editorPanel" hidden>
            <header class="article-editor__panel-head">
                <strong><?= e(__('articles.editor.panel_title')) ?></strong>
                <button type="button" class="article-editor__panel-close" id="editorPanelClose" aria-label="<?= e(__('articles.editor.close')) ?>">&times;</button>
            </header>
            <div class="article-editor__panel-body">
                <p class="muted article-editor__panel-context" id="editorPanelContext"></p>
                <textarea class="input article-editor__panel-input"
                          id="editorPanelInput"
                          rows="3"
                          placeholder="<?= e(__('articles.editor.prompt_placeholder')) ?>"></textarea>
                <div class="btn-row">
                    <button type="button" class="btn btn--primary btn--sm" id="editorPanelSend"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        <?= e(__('articles.editor.ask_btn')) ?>
                    </button>
                </div>
                <div class="article-editor__proposal" id="editorProposal" hidden>
                    <p class="article-editor__proposal-explanation" id="editorProposalExplanation"></p>
                    <div class="article-editor__proposal-preview" id="editorProposalPreview"></div>
                    <div class="btn-row">
                        <button type="button" class="btn btn--primary btn--sm" id="editorAccept">
                            &#10003; <?= e(__('articles.editor.accept')) ?>
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" id="editorReject">
                            &times; <?= e(__('articles.editor.reject')) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TinyMCE (open-source, MIT) via jsDelivr. -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.4.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    'use strict';
    var root = document.getElementById('articleEditor');
    if (!root || typeof tinymce === 'undefined') return;

    var articleId = root.getAttribute('data-article-id');
    var csrf      = root.getAttribute('data-csrf');
    var saveUrl   = '/tools/articles/' + articleId + '/edit/save';
    var imageUrl  = '/tools/articles/' + articleId + '/edit/image';
    var copilotUrl = '/tools/articles/' + articleId + '/edit/copilot';

    var saveState = document.getElementById('editorSaveState');
    var bubble    = document.getElementById('editorBubble');
    var panel     = document.getElementById('editorPanel');
    var panelCtx  = document.getElementById('editorPanelContext');
    var panelInput= document.getElementById('editorPanelInput');
    var sendBtn   = document.getElementById('editorPanelSend');
    var proposal  = document.getElementById('editorProposal');
    var explainEl = document.getElementById('editorProposalExplanation');
    var previewEl = document.getElementById('editorProposalPreview');
    var acceptBtn = document.getElementById('editorAccept');
    var rejectBtn = document.getElementById('editorReject');
    var closeBtn  = document.getElementById('editorPanelClose');

    var labels = {
        saved:     <?= json_encode(__('articles.editor.saved_now')) ?>,
        saving:    <?= json_encode(__('articles.editor.saving')) ?>,
        unsaved:   <?= json_encode(__('articles.editor.unsaved')) ?>,
        error:     <?= json_encode(__('copilot.error')) ?>,
        empty:     <?= json_encode(__('articles.editor.prompt_empty')) ?>,
        ctxSel:    <?= json_encode(__('articles.editor.ctx_selection')) ?>,
        ctxDoc:    <?= json_encode(__('articles.editor.ctx_document')) ?>,
        copyFail:  <?= json_encode(__('articles.editor.image_too_large')) ?>
    };

    var savedSelection = null;     // bookmark for the active selection
    var pendingProposal = null;    // last Copilot proposal awaiting user decision

    tinymce.init({
        target: document.getElementById('articleEditorTextarea'),
        license_key: 'gpl',
        plugins: 'autolink lists link image table code charmap searchreplace visualblocks wordcount fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image table | alignleft aligncenter alignright | searchreplace | code fullscreen',
        menubar: 'edit insert format table',
        height: 720,
        branding: false,
        promotion: false,
        statusbar: true,
        valid_elements: 'p,h1,h2,h3,h4,h5,h6,strong,b,em,i,u,s,br,a[href|target|rel],ul,ol,li,blockquote,table[border|cellpadding|cellspacing|class],thead,tbody,tr,th[colspan|rowspan|scope],td[colspan|rowspan],hr,img[src|alt|width|height|title],code,pre,sup,sub,span[style|class]',
        relative_urls: false,
        convert_urls: false,
        paste_data_images: false,
        images_upload_handler: function (blobInfo) {
            return new Promise(function (resolve, reject) {
                if (blobInfo.blob().size > 10 * 1024 * 1024) {
                    reject({ message: labels.copyFail, remove: true });
                    return;
                }
                var fd = new FormData();
                fd.append('file', blobInfo.blob(), blobInfo.filename());
                fd.append('_csrf', csrf);
                fetch(imageUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (body) {
                    if (body && body.ok && body.location) resolve(body.location);
                    else reject({ message: (body && body.error) || 'upload failed', remove: true });
                })
                .catch(function () { reject({ message: 'upload failed', remove: true }); });
            });
        },
        setup: function (editor) {
            editor.on('init', function () {
                saveState.textContent = labels.saved;
            });

            // ── Autosave ───────────────────────────────────────────────
            var saveTimer = null;
            editor.on('input change SetContent', function () {
                saveState.textContent = labels.unsaved;
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(function () { autosave(editor); }, 1500);
            });
            window.addEventListener('beforeunload', function () {
                if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
                if (saveState.textContent === labels.unsaved || saveState.textContent === labels.saving) {
                    // Synchronous beacon-style save on unload.
                    try {
                        navigator.sendBeacon(
                            saveUrl,
                            new Blob([JSON.stringify({ _csrf: csrf, html: editor.getContent() })],
                                     { type: 'application/json' })
                        );
                    } catch (e) {}
                }
            });

            // ── Selection bubble ───────────────────────────────────────
            editor.on('SelectionChange MouseUp KeyUp', function () {
                var sel = editor.selection.getContent({ format: 'text' });
                if (sel && sel.trim() !== '') {
                    positionBubble(editor);
                } else if (panel.hidden) {
                    bubble.hidden = true;
                }
            });
            editor.on('blur', function () {
                // Don't hide if the bubble itself is what got focus.
                setTimeout(function () {
                    if (!document.activeElement || (!root.contains(document.activeElement))) {
                        bubble.hidden = true;
                    }
                }, 80);
            });
        }
    });

    function positionBubble(editor) {
        var rng = editor.selection.getRng();
        if (!rng || !rng.getBoundingClientRect) { bubble.hidden = true; return; }
        var rect = rng.getBoundingClientRect();
        if (!rect || (rect.width === 0 && rect.height === 0)) { bubble.hidden = true; return; }
        var iframe = editor.getContainer().querySelector('iframe');
        var iframeRect = iframe ? iframe.getBoundingClientRect() : { top: 0, left: 0 };
        var top = iframeRect.top + rect.top + window.scrollY - 40;
        var left = iframeRect.left + rect.left + window.scrollX + (rect.width / 2) - 80;
        bubble.style.top = Math.max(window.scrollY + 60, top) + 'px';
        bubble.style.left = Math.max(8, left) + 'px';
        bubble.hidden = false;
    }

    bubble.addEventListener('click', function () {
        openPanel(true);
    });
    closeBtn.addEventListener('click', closePanel);

    function openPanel(withSelection) {
        var editor = tinymce.activeEditor;
        if (!editor) return;
        savedSelection = editor.selection.getBookmark(2, true);
        var sel = withSelection ? editor.selection.getContent({ format: 'text' }) : '';
        panelCtx.textContent = sel && sel.trim() !== ''
            ? (labels.ctxSel + ' « ' + truncate(sel, 140) + ' »')
            : labels.ctxDoc;
        hideProposal();
        panel.hidden = false;
        bubble.hidden = true;
        panelInput.value = '';
        panelInput.focus();
    }
    function closePanel() {
        panel.hidden = true;
        savedSelection = null;
        pendingProposal = null;
    }

    sendBtn.addEventListener('click', function () {
        var prompt = (panelInput.value || '').trim();
        if (!prompt) {
            panelInput.focus();
            return;
        }
        var editor = tinymce.activeEditor;
        if (!editor) return;
        if (savedSelection) editor.selection.moveToBookmark(savedSelection);
        var selectionHtml = editor.selection.getContent({ format: 'html' }) || '';
        var fullHtml = editor.getContent();

        sendBtn.disabled = true;
        var originalLabel = sendBtn.textContent;
        sendBtn.textContent = sendBtn.getAttribute('data-busy-label') || originalLabel;

        fetch(copilotUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({
                _csrf: csrf,
                prompt: prompt,
                full_html: fullHtml,
                selection_html: selectionHtml
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            if (body && body.ok && body.data && typeof body.data.html === 'string') {
                showProposal(body.data);
            } else {
                showError((body && body.error) || labels.error);
            }
        })
        .catch(function () { showError(labels.error); })
        .finally(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = originalLabel;
        });
    });

    function showProposal(data) {
        pendingProposal = data;
        explainEl.textContent = data.explanation || '';
        previewEl.innerHTML = data.html;
        proposal.hidden = false;
    }
    function hideProposal() {
        proposal.hidden = true;
        previewEl.innerHTML = '';
        explainEl.textContent = '';
        pendingProposal = null;
    }
    function showError(msg) {
        hideProposal();
        explainEl.textContent = String(msg);
        proposal.hidden = false;
        previewEl.innerHTML = '';
    }

    acceptBtn.addEventListener('click', function () {
        if (!pendingProposal) { hideProposal(); return; }
        var editor = tinymce.activeEditor;
        if (!editor) return;
        if (savedSelection) editor.selection.moveToBookmark(savedSelection);
        var html = pendingProposal.html;
        switch (pendingProposal.action) {
            case 'insert_after_selection':
                // Move caret to end of selection then insert.
                editor.selection.collapse(false);
                editor.insertContent('<p></p>' + html);
                break;
            case 'append_to_document':
                editor.setContent(editor.getContent() + html);
                break;
            case 'replace_selection':
            default:
                editor.selection.setContent(html);
                break;
        }
        editor.undoManager.add();
        hideProposal();
        panelInput.value = '';
        autosave(editor);
    });
    rejectBtn.addEventListener('click', function () {
        hideProposal();
        panelInput.focus();
    });

    function autosave(editor) {
        saveState.textContent = labels.saving;
        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf, html: editor.getContent() })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            saveState.textContent = (body && body.ok) ? labels.saved : labels.unsaved;
        })
        .catch(function () { saveState.textContent = labels.unsaved; });
    }

    function truncate(s, n) {
        s = String(s).replace(/\s+/g, ' ').trim();
        return s.length > n ? s.substring(0, n) + '…' : s;
    }
})();
</script>
