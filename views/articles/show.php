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
    <div class="page__head page__head--row">
        <div>
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

    <div class="article-workspace">
        <!-- LEFT: article text — collapsible card capped at viewport height,
             body itself scrolls so the page never exceeds the screen. -->
        <details class="article-pane article-pane--text section-card" open
                 aria-label="<?= e(__('articles.text_pane_label')) ?>">
            <summary class="article-pane__head">
                <h2 class="section__subtitle"><?= e(__('articles.text_pane_title')) ?></h2>
                <?php if (trim($text) === ''): ?>
                    <span class="tag tag--warn"><?= e(__('articles.no_text_extracted_tag')) ?></span>
                <?php endif; ?>
                <span class="article-pane__chevron" aria-hidden="true">&#9662;</span>
            </summary>
            <div class="article-pane__text">
<?php if (trim($text) !== ''): ?>
<pre class="article-pane__pre"><?= e($text) ?></pre>
<?php else: ?>
                <p class="muted"><?= e(__('articles.no_text_extracted')) ?></p>
<?php endif; ?>
            </div>
        </details>

        <!-- RIGHT: chat — same height as the article pane; the messages
             container scrolls inside, the input row stays pinned. -->
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
