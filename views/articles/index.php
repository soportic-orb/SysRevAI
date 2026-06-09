<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array<int,array<string,mixed>> $articles */
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title"><?= e(__('articles.index_title')) ?></h1>
            <p class="page__subtitle"><?= e(__('articles.index_intro')) ?></p>
        </div>
        <div class="btn-row">
            <a class="btn btn--primary" href="/tools/articles/new"><?= e(__('articles.new_btn')) ?></a>
        </div>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>
    <?php if (($warn = Session::pullFlash('warning')) !== null): ?>
        <div class="alert alert--warn"><?= e((string) $warn) ?></div>
    <?php endif; ?>

    <?php if ($articles === []): ?>
        <div class="empty-state">
            <p><?= e(__('articles.index_empty')) ?></p>
            <a class="btn btn--primary" href="/tools/articles/new"><?= e(__('articles.new_btn')) ?></a>
        </div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('articles.col_title')) ?></th>
                        <th><?= e(__('articles.col_source')) ?></th>
                        <th><?= e(__('articles.col_size')) ?></th>
                        <th><?= e(__('articles.col_updated')) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($articles as $a): ?>
                            <tr>
                                <td><a href="/tools/articles/<?= (int) $a['id'] ?>"><strong><?= e((string) ($a['title'] ?: '—')) ?></strong></a></td>
                                <td class="muted"><?= e((string) ($a['source_filename'] ?? '')) ?></td>
                                <td class="muted"><?= e(__('articles.size_chars', (int) ($a['char_count'] ?? 0))) ?></td>
                                <td class="muted"><?= e((string) ($a['updated_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
