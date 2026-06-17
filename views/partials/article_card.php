<?php

declare(strict_types=1);

/** @var array $article */
$id     = (int) $article['id'];
$title  = (string) ($article['title'] ?: '—');
$source = (string) ($article['source_filename'] ?? '');
$chars  = (int)    ($article['char_count']     ?? 0);
?>
<a class="article-card" href="/tools/articles/<?= $id ?>">
    <div class="article-card__head">
        <h3 class="article-card__title"><?= e($title) ?></h3>
    </div>
    <?php if ($source !== ''): ?>
        <p class="article-card__source muted"><?= e($source) ?></p>
    <?php endif; ?>
    <div class="article-card__meta">
        <?php if ($chars > 0): ?>
            <span class="tag tag--soft"><?= e(__('articles.size_chars', $chars)) ?></span>
        <?php endif; ?>
        <?php if (!empty($article['updated_at'])): ?>
            <span class="muted"><?= e((string) $article['updated_at']) ?></span>
        <?php endif; ?>
    </div>
</a>
