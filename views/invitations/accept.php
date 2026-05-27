<?php

declare(strict_types=1);

/** @var array $invitation */
/** @var ?array $review */
?>
<section class="auth-card auth-card--center">
    <h1 class="auth-card__title"><?= e(__('invite.title')) ?></h1>
    <p class="lead">
        <?= e(__('invite.body', $review['title'] ?? '—', (string) $invitation['role'])) ?>
    </p>
    <form method="post" action="/invite/<?= e((string) $invitation['token']) ?>/accept">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--primary btn--block"><?= e(__('invite.accept')) ?></button>
    </form>
    <p style="margin-top:14px"><a href="/dashboard"><?= e(__('invite.decline')) ?></a></p>
</section>
