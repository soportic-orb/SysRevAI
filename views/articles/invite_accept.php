<?php

declare(strict_types=1);

/** @var array $invitation */
/** @var ?array $article */
?>
<div class="auth-wrap">
    <div class="section-card">
        <h1 class="page__title"><?= e(__('articles.invite.title')) ?></h1>
        <p>
            <?= e(__('articles.invite.intro', (string) ($article['title'] ?? '—'))) ?>
        </p>
        <form method="post" action="/articles/invite/<?= e((string) $invitation['token']) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--primary" type="submit"><?= e(__('articles.invite.accept_btn')) ?></button>
            <a class="btn btn--ghost" href="/"><?= e(__('articles.invite.cancel')) ?></a>
        </form>
    </div>
</div>
