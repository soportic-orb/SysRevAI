<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array               $review     */
/** @var array<string,int>   $computed   */
/** @var array<string,int>   $overrides  */
/** @var string[]            $keys       */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <a class="back-link" href="/reviews/<?= $id ?>/exports"
                   title="<?= e(__('exports.title')) ?>"
                   aria-label="<?= e(__('exports.title')) ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </a>
                <?= e(__('exports.prisma_edit_title')) ?>
            </h1>
            <p class="page__subtitle muted"><?= e(__('exports.prisma_edit_intro')) ?></p>
        </div>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <form method="post" action="/reviews/<?= $id ?>/exports/prisma/edit" class="section-card">
        <?= csrf_field() ?>

        <p class="muted prisma-edit__hint">
            <?= e(__('exports.prisma_edit_hint')) ?>
        </p>

        <div class="prisma-edit__grid">
            <?php foreach ($keys as $key):
                $computedValue = (int) ($computed[$key] ?? 0);
                $pinned        = $overrides[$key] ?? null;
                $current       = $pinned ?? '';
            ?>
                <div class="prisma-edit__cell">
                    <label for="prisma_<?= e($key) ?>">
                        <?= e(__('exports.prisma_cell_' . $key)) ?>
                    </label>
                    <input class="input" id="prisma_<?= e($key) ?>" name="<?= e($key) ?>"
                           type="number" min="0" inputmode="numeric"
                           value="<?= e((string) $current) ?>"
                           placeholder="<?= e((string) $computedValue) ?>">
                    <small class="muted">
                        <?php if ($pinned !== null): ?>
                            <?= e(sprintf(__('exports.prisma_pinned_hint'), $computedValue)) ?>
                        <?php else: ?>
                            <?= e(sprintf(__('exports.prisma_computed_hint'), $computedValue)) ?>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="btn-row" style="margin-top:16px">
            <button type="submit" class="btn btn--primary">
                <?= e(__('exports.prisma_save')) ?>
            </button>
            <a class="btn btn--ghost" href="/reviews/<?= $id ?>/exports">
                <?= e(__('common.cancel')) ?>
            </a>
        </div>
    </form>

    <form method="post" action="/reviews/<?= $id ?>/exports/prisma/reset"
          class="section-card prisma-edit__reset"
          data-confirm="<?= e(__('exports.prisma_reset_confirm')) ?>"
          data-confirm-tone="danger"
          data-confirm-button="<?= e(__('exports.prisma_reset')) ?>">
        <?= csrf_field() ?>
        <p class="muted"><?= e(__('exports.prisma_reset_intro')) ?></p>
        <button type="submit" class="btn btn--danger btn--sm">
            <?= e(__('exports.prisma_reset')) ?>
        </button>
    </form>
</div>
