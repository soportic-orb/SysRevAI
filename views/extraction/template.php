<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Models\ExtractionTemplate;

/** @var array $review */
/** @var array $template */
/** @var array $fields */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/extraction"><?= e(__('extraction.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('extraction.edit_template')) ?></h1>
        <p class="page__subtitle"><?= e(__('extraction.template_intro')) ?></p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="/reviews/<?= $id ?>/extraction/template" class="form-grid section-card">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="name"><?= e(__('extraction.template_name')) ?></label>
            <input class="input" id="name" name="name" value="<?= e((string) ($template['name'] ?? 'Default extraction')) ?>" required>
        </div>

        <div id="fieldsContainer">
            <?php foreach ($fields as $i => $field): ?>
                <div class="tpl-row" data-row>
                    <div class="form-row form-row--split">
                        <div class="field">
                            <label class="field-label"><?= e(__('extraction.field_key')) ?></label>
                            <input class="input" name="fields[<?= $i ?>][key]" value="<?= e((string) ($field['key'] ?? '')) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field-label"><?= e(__('extraction.field_label')) ?></label>
                            <input class="input" name="fields[<?= $i ?>][label]" value="<?= e((string) ($field['label'] ?? '')) ?>" required>
                        </div>
                        <div class="field field--narrow">
                            <label class="field-label"><?= e(__('extraction.field_type')) ?></label>
                            <select class="select" name="fields[<?= $i ?>][type]" data-type>
                                <?php foreach (ExtractionTemplate::FIELD_TYPES as $t): ?>
                                    <option value="<?= $t ?>" <?= ($field['type'] ?? 'text') === $t ? 'selected' : '' ?>><?= e(__('extraction.type_' . $t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field tpl-options" <?= in_array(($field['type'] ?? 'text'), ['select','multi_select'], true) ? '' : 'hidden' ?>>
                        <label class="field-label"><?= e(__('extraction.field_options')) ?></label>
                        <textarea class="input" name="fields[<?= $i ?>][options]" rows="3"><?= e(implode("\n", (array) ($field['options'] ?? []))) ?></textarea>
                        <span class="field-help"><?= e(__('extraction.options_help')) ?></span>
                    </div>
                    <div class="tpl-row__actions">
                        <button type="button" class="btn btn--ghost btn--sm" data-remove>&times; <?= e(__('extraction.remove_field')) ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions actions--start">
            <button type="button" class="btn btn--ghost" id="addField">+ <?= e(__('extraction.add_field')) ?></button>
            <button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    var container = document.getElementById('fieldsContainer');
    var addBtn = document.getElementById('addField');
    var types = <?= json_encode(ExtractionTemplate::FIELD_TYPES) ?>;
    var labels = <?= json_encode(array_combine(ExtractionTemplate::FIELD_TYPES, array_map(static fn ($t) => __('extraction.type_' . $t), ExtractionTemplate::FIELD_TYPES))) ?>;
    var keyLabel = <?= json_encode(__('extraction.field_key')) ?>;
    var lblLabel = <?= json_encode(__('extraction.field_label')) ?>;
    var typeLabel = <?= json_encode(__('extraction.field_type')) ?>;
    var optsLabel = <?= json_encode(__('extraction.field_options')) ?>;
    var optsHelp = <?= json_encode(__('extraction.options_help')) ?>;
    var removeLabel = <?= json_encode(__('extraction.remove_field')) ?>;

    function nextIndex() {
        return container.querySelectorAll('[data-row]').length;
    }

    addBtn.addEventListener('click', function () {
        var i = nextIndex();
        var opts = types.map(function (t) { return '<option value="' + t + '">' + labels[t] + '</option>'; }).join('');
        var div = document.createElement('div');
        div.className = 'tpl-row';
        div.setAttribute('data-row', '');
        div.innerHTML =
            '<div class="form-row form-row--split">' +
              '<div class="field"><label class="field-label">' + keyLabel + '</label><input class="input" name="fields[' + i + '][key]" required></div>' +
              '<div class="field"><label class="field-label">' + lblLabel + '</label><input class="input" name="fields[' + i + '][label]" required></div>' +
              '<div class="field field--narrow"><label class="field-label">' + typeLabel + '</label><select class="select" name="fields[' + i + '][type]" data-type>' + opts + '</select></div>' +
            '</div>' +
            '<div class="field tpl-options" hidden><label class="field-label">' + optsLabel + '</label><textarea class="input" name="fields[' + i + '][options]" rows="3"></textarea><span class="field-help">' + optsHelp + '</span></div>' +
            '<div class="tpl-row__actions"><button type="button" class="btn btn--ghost btn--sm" data-remove>&times; ' + removeLabel + '</button></div>';
        container.appendChild(div);
    });

    container.addEventListener('click', function (e) {
        if (e.target.matches('[data-remove]')) {
            e.target.closest('[data-row]').remove();
        }
    });

    container.addEventListener('change', function (e) {
        if (e.target.matches('[data-type]')) {
            var row = e.target.closest('[data-row]');
            var opts = row.querySelector('.tpl-options');
            var needs = e.target.value === 'select' || e.target.value === 'multi_select';
            opts.hidden = !needs;
        }
    });
})();
</script>
