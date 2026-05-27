<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var string[] $formats */
/** @var array $logs */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
        <h1 class="page__title"><?= e(__('import.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('import.intro')) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="/reviews/<?= $id ?>/import" class="form-grid section-card" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="format"><?= e(__('import.format')) ?></label>
            <select class="select" id="format" name="format">
                <option value="auto"><?= e(__('import.auto_detect')) ?></option>
                <?php foreach ($formats as $f): ?>
                    <option value="<?= e($f) ?>"><?= e(__('import.fmt_' . $f)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="file"><?= e(__('import.file')) ?></label>
            <input class="input" id="file" name="file" type="file" accept=".ris,.nbib,.bib,.csv,.xml,.enw,.txt">
            <span class="field-help"><?= e(__('import.file_help')) ?></span>
        </div>

        <div class="field">
            <label class="field-label" for="paste"><?= e(__('import.paste')) ?></label>
            <textarea class="input" id="paste" name="paste" rows="6" placeholder="<?= e(__('import.paste_help')) ?>"></textarea>
        </div>

        <div><button type="submit" class="btn btn--primary"><?= e(__('import.submit')) ?></button></div>
    </form>

    <?php if ($logs !== []): ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('import.history')) ?></h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(__('import.file')) ?></th><th><?= e(__('import.format')) ?></th><th><?= e(__('import.imported')) ?></th><th><?= e(__('import.duplicates')) ?></th><th><?= e(__('admin.maintenance.when')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e((string) $log['filename']) ?></td>
                                <td><?= e((string) $log['format']) ?></td>
                                <td><?= (int) $log['total_imported'] ?></td>
                                <td><?= (int) $log['total_duplicates'] ?></td>
                                <td class="muted"><?= e((string) $log['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
