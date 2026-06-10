<?php

declare(strict_types=1);

/**
 * Article action toolbar — same set of buttons that live in the workspace
 * header. Used on every page inside the article module so the user can
 * jump between workspace, editor, critical report, export, team and
 * download without going back through a breadcrumb.
 *
 * Workspace / Editor / Critical report keep their text labels. Team,
 * Export, Download original and Delete are icon-only — their text label
 * survives as aria-label + title so screen readers and hover-tooltips
 * still announce the action.
 *
 * @var array       $article            The article row.
 * @var bool        $isOwner            Whether the current user owns the article.
 * @var string|null $articleActionsActive  Optional: one of 'workspace',
 *     'editor', 'critical-report', 'export', 'team' to highlight the
 *     matching button.
 */
$id = (int) $article['id'];
$active = (string) ($articleActionsActive ?? '');

/* Helper for the labelled buttons (workspace / editor / critical report). */
$btn = static function (string $key, string $href, string $label) use ($active): string {
    $cls = $key === $active ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
    return '<a class="' . $cls . '" href="' . e($href) . '">' . $label . '</a>';
};

/* Helper for the icon-only buttons. Captures the same active-tab
 * highlight pattern, exposes the label via aria-label + title and
 * renders the requested Tabler glyph inline. */
$iconBtn = static function (string $key, string $href, string $iconName, string $label, string $extraClass = '') use ($active): void {
    $cls = ($key === $active ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm') . ' btn--icon';
    if ($extraClass !== '') {
        $cls .= ' ' . $extraClass;
    }
    echo '<a class="' . e($cls) . '" href="' . e($href)
        . '" aria-label="' . e($label) . '" title="' . e($label) . '">';
    $iconClass = 'icon-action';
    require config('paths.base') . '/views/partials/icon.php';
    echo '</a>';
};
?>
<div class="btn-row article-actions">
    <?= $btn('workspace', '/tools/articles/' . $id, e(__('articles.workspace_btn'))) ?>
    <?= $btn('editor', '/tools/articles/' . $id . '/edit', e(__('articles.editor.btn'))) ?>
    <?= $btn(
        'critical-report',
        '/tools/articles/' . $id . '/critical-report',
        e(__('articles.critical.cta'))
    ) ?>

    <?php $iconBtn('team',   '/tools/articles/' . $id . '/team',     'team_group',  __('articles.team_btn')); ?>
    <?php $iconBtn('export', '/tools/articles/' . $id . '/export',   'file_export', __('articles.export.btn')); ?>
    <?php $iconBtn('',       '/tools/articles/' . $id . '/download', 'download',    __('articles.download_btn')); ?>

    <?php if ($isOwner): ?>
        <form method="post" action="/tools/articles/<?= $id ?>/delete"
              class="inline-form"
              data-confirm="<?= e(__('articles.delete_confirm')) ?>"
              data-confirm-tone="danger"
              data-confirm-button="<?= e(__('articles.delete_btn')) ?>">
            <?= csrf_field() ?>
            <button type="submit"
                    class="btn btn--ghost btn--sm btn--danger btn--icon"
                    aria-label="<?= e(__('articles.delete_btn')) ?>"
                    title="<?= e(__('articles.delete_btn')) ?>">
                <?php $iconName = 'trash_x'; $iconClass = 'icon-action'; require config('paths.base') . '/views/partials/icon.php'; ?>
            </button>
        </form>
    <?php endif; ?>
</div>
