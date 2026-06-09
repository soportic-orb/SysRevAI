<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Services\ArticleExportService;

/** @var array     $article */
/** @var bool      $isOwner */
/** @var string[]  $scopes */
$id = (int) $article['id'];
?>
<div class="page article-export">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <?= e((string) ($article['title'] ?: '—')) ?>
                <span class="muted">— <?= e(__('articles.export.title')) ?></span>
            </h1>
            <p class="page__subtitle muted"><?= e(__('articles.export.subtitle')) ?></p>
        </div>
        <?php
            $articleActionsActive = 'export';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form class="section-card article-export__form" method="get" id="article-export-form"
          action="/tools/articles/<?= $id ?>/export/docx">
        <fieldset class="article-export__fieldset">
            <legend class="section__subtitle"><?= e(__('articles.export.scope_legend')) ?></legend>
            <?php foreach ($scopes as $scope):
                $isReport = $scope === ArticleExportService::SCOPE_REPORT;
            ?>
                <label class="article-export__radio">
                    <input type="radio" name="scope" value="<?= e($scope) ?>"
                           <?= $isReport ? 'checked' : '' ?>>
                    <span class="article-export__radio-label">
                        <strong><?= e(__('articles.export.scope_' . $scope)) ?></strong>
                        <span class="muted"><?= e(__('articles.export.scope_' . $scope . '_hint')) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="article-export__fieldset">
            <legend class="section__subtitle"><?= e(__('articles.export.format_legend')) ?></legend>
            <div class="btn-row">
                <button type="submit" formaction="/tools/articles/<?= $id ?>/export/docx"
                        class="btn btn--primary">
                    <?= e(__('articles.export.download_docx')) ?>
                </button>
                <button type="submit" formaction="/tools/articles/<?= $id ?>/export/pdf"
                        class="btn btn--primary">
                    <?= e(__('articles.export.download_pdf')) ?>
                </button>
            </div>
        </fieldset>
    </form>
</div>
