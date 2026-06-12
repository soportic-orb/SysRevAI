<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $article */
/** @var bool  $isOwner */
/** @var array $members */
/** @var array $history */

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

    <div class="article-workspace" id="articleWorkspace" data-article-id="<?= $id ?>">
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
