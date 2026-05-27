<?php

declare(strict_types=1);

/** @var array $notifications */
/** @var bool $onlyUnread */
?>
<div class="page page--narrow">
    <div class="page__head page__head--row">
        <h1 class="page__title"><?= e(__('nav.notifications')) ?></h1>
        <form method="post" action="/notifications/read-all">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm"><?= e(__('notifications.mark_all')) ?></button>
        </form>
    </div>

    <div class="tabs">
        <a class="tab <?= $onlyUnread ? '' : 'is-active' ?>" href="/notifications"><?= e(__('notifications.all')) ?></a>
        <a class="tab <?= $onlyUnread ? 'is-active' : '' ?>" href="/notifications?filter=unread"><?= e(__('notifications.unread')) ?></a>
    </div>

    <?php if ($notifications === []): ?>
        <div class="empty-state"><p><?= e(__('notifications.empty')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <ul class="notif-list">
                <?php foreach ($notifications as $n): ?>
                    <li class="notif-item <?= (int) $n['is_read'] === 0 ? 'is-unread' : '' ?>">
                        <form method="post" action="/notifications/read" class="notif-item__form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= e((string) ($n['action_url'] ?? '/notifications')) ?>">
                            <button type="submit" class="notif-item__btn">
                                <strong><?= e((string) $n['title']) ?></strong>
                                <?php if (!empty($n['message'])): ?>
                                    <span class="notif-item__msg"><?= e(mb_strimwidth((string) $n['message'], 0, 160, '…')) ?></span>
                                <?php endif; ?>
                                <span class="notif-item__time muted"><?= e((string) $n['created_at']) ?></span>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
