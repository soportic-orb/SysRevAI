<?php

declare(strict_types=1);

/** Shared left-rail navigation for the /profile/* pages.
 *  @var string $active */
$tabs = [
    'profile'       => ['/profile',                __('profile.tab_profile')],
    'password'      => ['/profile/password',       __('profile.tab_password')],
    'two_factor'    => ['/profile/two-factor',     __('profile.tab_2fa')],
    'notifications' => ['/profile/notifications',  __('profile.tab_notifications')],
];
?>
<aside class="profile-nav">
    <h2 class="profile-nav__title"><?= e(__('profile.title')) ?></h2>
    <nav>
        <?php foreach ($tabs as $key => [$url, $label]): ?>
            <a href="<?= e($url) ?>" class="profile-nav__link <?= $key === $active ? 'is-active' : '' ?>">
                <?= e((string) $label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
