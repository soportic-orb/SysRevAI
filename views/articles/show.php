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
<div class="page article-page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb">
                <a href="/tools/articles"><?= e(__('articles.index_title')) ?></a> /
            </div>
            <h1 class="page__title"><?= e((string) ($article['title'] ?: '—')) ?></h1>
            <p class="page__subtitle muted">
                <?php if (!empty($article['source_filename'])): ?>
                    <?= e((string) $article['source_filename']) ?>
                <?php endif; ?>
                · <?= e(__('articles.size_chars', (int) ($article['char_count'] ?? 0))) ?>
                · <?= count($members) > 0 ? e(__('articles.team_count', count($members) + 1)) : e(__('articles.solo')) ?>
            </p>
        </div>
        <div class="btn-row">
            <a class="btn btn--ghost btn--sm" href="/tools/articles/<?= $id ?>/team"><?= e(__('articles.team_btn')) ?></a>
            <a class="btn btn--ghost btn--sm" href="/tools/articles/<?= $id ?>/download"><?= e(__('articles.download_btn')) ?></a>
            <?php if ($isOwner): ?>
                <form method="post" action="/tools/articles/<?= $id ?>/delete"
                      style="display:inline"
                      data-confirm="<?= e(__('articles.delete_confirm')) ?>"
                      data-confirm-tone="danger"
                      data-confirm-button="<?= e(__('articles.delete_btn')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                        <?= e(__('articles.delete_btn')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($warn = Session::pullFlash('warning')) !== null): ?>
        <div class="alert alert--warn"><?= e((string) $warn) ?></div>
    <?php endif; ?>

    <div class="article-workspace">
        <!-- LEFT: article text -->
        <section class="article-pane article-pane--text section-card" aria-label="<?= e(__('articles.text_pane_label')) ?>">
            <div class="article-pane__head">
                <h2 class="section__subtitle"><?= e(__('articles.text_pane_title')) ?></h2>
                <?php if (trim($text) === ''): ?>
                    <span class="tag tag--warn"><?= e(__('articles.no_text_extracted_tag')) ?></span>
                <?php endif; ?>
            </div>
            <div class="article-pane__text">
<?php if (trim($text) !== ''): ?>
<pre class="article-pane__pre"><?= e($text) ?></pre>
<?php else: ?>
                <p class="muted"><?= e(__('articles.no_text_extracted')) ?></p>
<?php endif; ?>
            </div>
        </section>

        <!-- RIGHT: chat -->
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
