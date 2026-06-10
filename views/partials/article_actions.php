<?php

declare(strict_types=1);

/**
 * Article action toolbar — same five buttons that live in the workspace
 * header. Used on every page inside the article module so the user can
 * jump between workspace, critical report, export, team and download
 * without going back through a breadcrumb.
 *
 * @var array       $article            The article row.
 * @var bool        $isOwner            Whether the current user owns the article.
 * @var string|null $articleActionsActive  Optional: one of 'workspace',
 *     'critical-report', 'export', 'team' to highlight the matching button.
 */
$id = (int) $article['id'];
$active = (string) ($articleActionsActive ?? '');
$btn = static function (string $key, string $href, string $label, string $extra = '') use ($active): string {
    $cls = $key === $active ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm';
    return '<a class="' . $cls . '" href="' . e($href) . '" ' . $extra . '>' . $label . '</a>';
};
?>
<div class="btn-row article-actions">
    <?= $btn('workspace', '/tools/articles/' . $id, e(__('articles.workspace_btn'))) ?>
    <?= $btn('editor', '/tools/articles/' . $id . '/edit', e(__('articles.editor.btn'))) ?>
    <?= $btn(
        'critical-report',
        '/tools/articles/' . $id . '/critical-report',
        '&#10024; ' . e(__('articles.critical.cta'))
    ) ?>
    <?= $btn('export', '/tools/articles/' . $id . '/export', e(__('articles.export.btn'))) ?>
    <?= $btn('team', '/tools/articles/' . $id . '/team', e(__('articles.team_btn'))) ?>
    <a class="btn btn--ghost btn--sm" href="/tools/articles/<?= $id ?>/download">
        <?= e(__('articles.download_btn')) ?>
    </a>
    <?php if ($isOwner): ?>
        <form method="post" action="/tools/articles/<?= $id ?>/delete"
              style="display:inline"
              data-confirm="<?= e(__('articles.delete_confirm')) ?>"
              data-confirm-tone="danger"
              data-confirm-button="<?= e(__('articles.delete_btn')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                <?= e(__('articles.delete_btn')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>
