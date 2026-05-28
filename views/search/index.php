<?php

declare(strict_types=1);

/** @var string $q */
/** @var array $results */
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('search.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('search.intro')) ?></p>
    </div>

    <form method="get" action="/search" class="toolbar">
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('search.placeholder')) ?>" autofocus>
        <button class="btn btn--primary"><?= e(__('search.go')) ?></button>
    </form>

    <?php if ($q === ''): ?>
        <p class="muted"><?= e(__('search.hint')) ?></p>
    <?php elseif ($results === []): ?>
        <div class="empty-state"><p><?= e(__('search.no_results', $q)) ?></p></div>
    <?php else: ?>
        <p class="muted"><?= e(__('search.count', count($results))) ?></p>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(__('references.col_study')) ?></th><th><?= e(__('search.review')) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($results as $r): $authors = json_decode((string) $r['authors_json'], true) ?: []; ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                    <span class="muted">
                                        <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                        <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                        <?php if (!empty($r['journal'])): ?> · <em><?= e((string) $r['journal']) ?></em><?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/reviews/<?= (int) $r['review_id'] ?>"><?= e((string) $r['review_title']) ?></a>
                                    <br><span class="tag tag--soft"><?= e(__('references.st_' . $r['status'])) ?></span>
                                </td>
                                <td><a class="btn btn--ghost btn--sm" href="/reviews/<?= (int) $r['review_id'] ?>/references/<?= (int) $r['id'] ?>/summary"><?= e(__('summary.title')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
