<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Models\ExtractionData;

/** @var array $review */
/** @var array $reference */
/** @var array $template */
/** @var array $fields */
/** @var ?array $own */
/** @var array $data */
/** @var array $others */
/** @var bool $isOwner */
$id = (int) $review['id'];
$refId = (int) $reference['id'];
$authors = json_decode((string) $reference['authors_json'], true) ?: [];

$renderField = static function (array $f, mixed $val): void {
    $key = (string) $f['key'];
    $type = (string) ($f['type'] ?? 'text');
    $name = e($key);

    if ($type === 'textarea') {
        echo '<textarea class="input" name="' . $name . '" rows="3">' . e((string) ($val ?? '')) . '</textarea>';
    } elseif ($type === 'number') {
        echo '<input class="input" type="number" step="any" name="' . $name . '" value="' . e((string) ($val ?? '')) . '">';
    } elseif ($type === 'date') {
        echo '<input class="input" type="date" name="' . $name . '" value="' . e((string) ($val ?? '')) . '">';
    } elseif ($type === 'select') {
        echo '<select class="select" name="' . $name . '"><option value="">—</option>';
        foreach ((array) ($f['options'] ?? []) as $o) {
            $sel = ((string) $val === (string) $o) ? ' selected' : '';
            echo '<option value="' . e((string) $o) . '"' . $sel . '>' . e((string) $o) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'multi_select') {
        $values = is_array($val) ? array_map('strval', $val) : [];
        echo '<select class="select" multiple size="4" name="' . $name . '[]">';
        foreach ((array) ($f['options'] ?? []) as $o) {
            $sel = in_array((string) $o, $values, true) ? ' selected' : '';
            echo '<option value="' . e((string) $o) . '"' . $sel . '>' . e((string) $o) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input class="input" type="text" name="' . $name . '" value="' . e((string) ($val ?? '')) . '">';
    }
};
?>
<div class="page">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/extraction"><?= e(__('extraction.title')) ?></a> /</div>
        <h1 class="page__title"><?= e((string) ($reference['title'] ?: '—')) ?></h1>
        <p class="muted">
            <?= e(implode('; ', array_slice($authors, 0, 4))) ?>
            <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
            <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
        </p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <div class="ft-layout">
        <section class="ft-main">
            <form method="post" action="/reviews/<?= $id ?>/extraction/<?= $refId ?>" class="form-grid section-card">
                <?= csrf_field() ?>

                <?php foreach ($fields as $f): ?>
                    <div class="field">
                        <label class="field-label"><?= e((string) ($f['label'] ?? $f['key'])) ?></label>
                        <?php $renderField($f, $data[$f['key']] ?? null); ?>
                    </div>
                <?php endforeach; ?>

                <div class="actions actions--start">
                    <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= e(__('extraction.save_draft')) ?></button>
                    <button type="submit" name="action" value="submit" class="btn btn--primary"><?= e(__('extraction.submit')) ?></button>
                </div>
            </form>

            <form method="post" action="/reviews/<?= $id ?>/extraction/<?= $refId ?>/ai" class="section-card section-card--inline" data-ai-action>
                <?= csrf_field() ?>
                <button class="btn btn--ghost">&#10024; <?= e(__('extraction.fill_ai')) ?></button>
                <span class="field-help"><?= e(__('extraction.fill_ai_help')) ?></span>
            </form>

            <?php if ($isOwner && $own !== null && ($own['status'] ?? '') === 'submitted'): ?>
                <form method="post" action="/reviews/<?= $id ?>/extraction/<?= $refId ?>/approve" class="section-card section-card--inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="extraction_id" value="<?= (int) $own['id'] ?>">
                    <button class="btn btn--include"><?= e(__('extraction.approve')) ?></button>
                </form>
            <?php endif; ?>
        </section>

        <aside class="ft-aside">
            <div class="section-card">
                <h3 class="section__subtitle"><?= e(__('extraction.your_status')) ?></h3>
                <p>
                    <?php $s = $own['status'] ?? null; ?>
                    <?php if ($s === null): ?>
                        <span class="muted"><?= e(__('extraction.st_none')) ?></span>
                    <?php else: ?>
                        <span class="tag tag--<?= e($s) ?>"><?= e(__('extraction.st_' . $s)) ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="section-card">
                <h3 class="section__subtitle"><?= e(__('extraction.compare')) ?></h3>
                <?php if ($others === []): ?>
                    <p class="muted"><?= e(__('extraction.no_others')) ?></p>
                <?php else: ?>
                    <?php foreach ($others as $o): $od = ExtractionData::decodeData($o); ?>
                        <details class="compare-row">
                            <summary>
                                <strong><?= e((string) $o['reviewer_name']) ?></strong>
                                <span class="tag tag--<?= e((string) $o['status']) ?>"><?= e(__('extraction.st_' . $o['status'])) ?></span>
                            </summary>
                            <dl class="kv">
                                <?php foreach ($fields as $f): $v = $od[$f['key']] ?? null; ?>
                                    <dt><?= e((string) ($f['label'] ?? $f['key'])) ?></dt>
                                    <dd><?= e(is_array($v) ? implode(', ', $v) : (string) ($v ?? '—')) ?></dd>
                                <?php endforeach; ?>
                            </dl>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
