<?php

declare(strict_types=1);

/** @var array<int,array<string,mixed>> $tools */
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('tools.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('tools.intro')) ?></p>
    </div>

    <div class="tools-grid">
        <?php foreach ($tools as $tool):
            $label  = __((string) $tool['labelKey']);
            $blurb  = __((string) $tool['blurbKey']);
            $route  = (string) $tool['route'];
            $status = (string) $tool['status'];
            $available = $status === 'available';
            $iconName  = (string) $tool['icon'];
            $tag = 'a';
            $attrs = '';
            if ($available) {
                $attrs = 'href="' . e($route) . '"';
            } else {
                $tag = 'div';
                $attrs = 'role="group" aria-disabled="true"';
            }
            $classes = 'tools-card' . ($available ? '' : ' tools-card--coming');
        ?>
            <<?= $tag ?> class="<?= e($classes) ?>" <?= $attrs ?>>
                <span class="tools-card__icon">
                    <?php $iconClass = 'tools-card__icon-svg'; require config('paths.base') . '/views/partials/icon.php'; ?>
                </span>
                <div class="tools-card__body">
                    <h2 class="tools-card__title">
                        <?= e($label) ?>
                        <?php if (!$available): ?>
                            <span class="tag tag--soft tools-card__tag">
                                <?= e(__('tools.coming_soon')) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p class="tools-card__blurb"><?= e($blurb) ?></p>
                </div>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
</div>
