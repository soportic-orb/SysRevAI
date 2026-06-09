<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
?>
<div class="page page--narrow">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('articles.new_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('articles.new_intro')) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="/tools/articles" enctype="multipart/form-data"
          class="form-grid section-card" data-ai-action>
        <?= csrf_field() ?>
        <div class="field">
            <label class="field-label" for="title"><?= e(__('articles.field_title')) ?></label>
            <input class="input" id="title" name="title" type="text" maxlength="500"
                   placeholder="<?= e(__('articles.field_title_help')) ?>">
            <span class="field-help"><?= e(__('articles.field_title_optional')) ?></span>
        </div>
        <div class="field">
            <label class="field-label" for="document"><?= e(__('articles.field_file')) ?></label>
            <input class="input" id="document" name="document" type="file"
                   accept=".pdf,.docx,.doc,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword"
                   required>
            <span class="field-help"><?= e(__('articles.field_file_help')) ?></span>
        </div>
        <div>
            <button type="submit" class="btn btn--primary"
                    data-busy-label="<?= e(__('common.working')) ?>">
                <?= e(__('articles.upload_btn')) ?>
            </button>
            <a class="btn btn--ghost" href="/tools/articles"><?= e(__('articles.cancel')) ?></a>
        </div>
    </form>
</div>
