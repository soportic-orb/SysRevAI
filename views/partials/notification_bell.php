<?php

declare(strict_types=1);

/** @var int $unread */
$unread = $unread ?? 0;
?>
<div class="bell" data-bell>
    <a href="/notifications" class="bell__btn" data-bell-toggle aria-label="<?= e(__('nav.notifications')) ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="bell__badge" data-bell-count <?= $unread > 0 ? '' : 'hidden' ?>><?= (int) $unread ?></span>
    </a>
    <div class="bell__dropdown" data-bell-panel hidden>
        <div class="bell__head"><?= e(__('nav.notifications')) ?></div>
        <div class="bell__list" data-bell-list></div>
        <a class="bell__all" href="/notifications"><?= e(__('notifications.view_all')) ?></a>
    </div>
</div>
