<?php

declare(strict_types=1);

use SysRevAI\Core\I18n;

$supported = I18n::allowedLocales();
$active    = (array) (setting('ui.active_locales') ?? ['ca', 'es', 'en']);
$custom    = (array) (setting('ui.custom_locales') ?? []);
$builtins  = (array) config('supported_locales', ['ca', 'es', 'en']);

$displayName = static function (string $code) use ($custom): string {
    static $names = [
        'ca' => 'Català', 'es' => 'Español', 'en' => 'English', 'fr' => 'Français',
        'de' => 'Deutsch', 'pt' => 'Português', 'it' => 'Italiano', 'eu' => 'Euskara', 'gl' => 'Galego',
    ];
    if (isset($custom[$code])) {
        return (string) $custom[$code];
    }
    return $names[$code] ?? strtoupper($code);
};

// Editor selection — read from the GET so the post-save redirect can return
// the admin to the exact same view they were editing.
$editLocale = (string) ($_GET['locale'] ?? 'ca');
if (!in_array($editLocale, $supported, true)) {
    $editLocale = 'ca';
}
// The fallback file (Catalan) is the source of truth for "every key the
// platform ships". A brand-new custom locale starts with no file at all,
// so we list the fallback's keys and let the admin fill each one in.
$fallbackMap = I18n::fileMap('ca');
$localeFileMap = $editLocale === 'ca' ? $fallbackMap : I18n::fileMap($editLocale);
$overrideMap = I18n::overrideMap($editLocale);

// Group keys by top-level segment (the "section" they belong to in the
// PHP file). The dropdown lists every group present in the fallback so
// custom locales can target everything.
$groups = [];
foreach (array_keys($fallbackMap) as $path) {
    $top = explode('.', $path)[0];
    $groups[$top] = true;
}
$groups = array_keys($groups);
sort($groups);

$editGroup = (string) ($_GET['group'] ?? '');
if ($editGroup !== '' && !in_array($editGroup, $groups, true)) {
    $editGroup = '';
}

$rows = [];
foreach ($fallbackMap as $path => $fallbackValue) {
    if ($editGroup !== '' && !str_starts_with($path, $editGroup . '.') && $path !== $editGroup) {
        continue;
    }
    $localeValue = $localeFileMap[$path] ?? null;
    $override    = $overrideMap[$path] ?? null;
    $rows[] = [
        'key'       => $path,
        'fallback'  => $fallbackValue,
        'localeVal' => $localeValue,
        'override'  => $override,
    ];
}
?>
<h1 class="section__title"><?= e(__('admin.sections.languages')) ?></h1>
<p class="section__intro"><?= e(__('admin.languages.intro')) ?></p>

<!-- Active-in-UI form (existing). -->
<form method="post" action="/admin/settings/languages" class="form-grid section-card">
    <?= csrf_field() ?>

    <fieldset class="toggles">
        <legend><?= e(__('admin.languages.active')) ?></legend>
        <div class="checkbox-grid">
            <?php foreach ($supported as $l): ?>
                <label class="checkbox">
                    <input type="checkbox" name="active_locales[]" value="<?= e($l) ?>" <?= in_array($l, $active, true) ? 'checked' : '' ?>>
                    <?= e($displayName($l)) ?>
                    <?php if (isset($custom[$l])): ?>
                        <span class="muted">· <?= e(__('admin.languages.custom_tag')) ?></span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>

<!-- Add custom locale. -->
<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.languages.add_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.languages.add_intro')) ?></p>
    <form method="post" action="/admin/languages/locale-add" class="form-grid">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field-label" for="lang_code"><?= e(__('admin.languages.code')) ?></label>
            <input class="input" id="lang_code" name="code" type="text" required
                   pattern="[A-Za-z]{2,3}([_-][A-Za-z]{2,4})?"
                   placeholder="<?= e(__('admin.languages.code_placeholder')) ?>"
                   maxlength="8">
            <span class="field-help"><?= e(__('admin.languages.code_help')) ?></span>
        </div>
        <div class="field">
            <label class="field-label" for="lang_name"><?= e(__('admin.languages.name')) ?></label>
            <input class="input" id="lang_name" name="name" type="text" required
                   placeholder="<?= e(__('admin.languages.name_placeholder')) ?>" maxlength="64">
        </div>
        <div><button type="submit" class="btn btn--primary"><?= e(__('admin.languages.add_btn')) ?></button></div>
    </form>

    <?php if ($custom !== []): ?>
        <p class="muted"><?= e(__('admin.languages.custom_list')) ?></p>
        <ul class="lang-custom-list">
            <?php foreach ($custom as $code => $name): ?>
                <li>
                    <strong><?= e((string) $name) ?></strong>
                    <span class="muted">(<?= e((string) $code) ?>)</span>
                    <form method="post" action="/admin/languages/locale-remove"
                          data-confirm="<?= e(__('admin.languages.remove_confirm', $name)) ?>"
                          data-confirm-tone="danger"
                          data-confirm-button="<?= e(__('admin.languages.remove_btn')) ?>"
                          style="display:inline; margin-left:8px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= e((string) $code) ?>">
                        <button type="submit" class="btn btn--ghost btn--xs btn--danger">
                            <?= e(__('admin.languages.remove_btn')) ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- String editor. -->
<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.languages.editor_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.languages.editor_intro')) ?></p>

    <form method="get" action="/admin/settings/languages" class="toolbar lang-editor__filters">
        <select class="select select--sm" name="locale" onchange="this.form.submit()"
                aria-label="<?= e(__('admin.languages.editor_locale')) ?>">
            <?php foreach ($supported as $l): ?>
                <option value="<?= e($l) ?>" <?= $editLocale === $l ? 'selected' : '' ?>>
                    <?= e($displayName($l)) ?> (<?= e($l) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <select class="select select--sm" name="group" onchange="this.form.submit()"
                aria-label="<?= e(__('admin.languages.editor_group')) ?>">
            <option value=""><?= e(__('admin.languages.editor_group_all')) ?></option>
            <?php foreach ($groups as $g): ?>
                <option value="<?= e($g) ?>" <?= $editGroup === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="muted lang-editor__count">
            <?= e(__('admin.languages.editor_count', count($rows))) ?>
        </span>
    </form>

    <form method="post" action="/admin/languages/save" class="lang-editor__form">
        <?= csrf_field() ?>
        <input type="hidden" name="locale" value="<?= e($editLocale) ?>">
        <input type="hidden" name="group" value="<?= e($editGroup) ?>">

        <?php if ($rows === []): ?>
            <p class="muted"><?= e(__('admin.languages.editor_empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table lang-editor__table">
                    <thead><tr>
                        <th class="lang-editor__col-key"><?= e(__('admin.languages.col_key')) ?></th>
                        <th class="lang-editor__col-default"><?= e(__('admin.languages.col_default')) ?></th>
                        <th class="lang-editor__col-value"><?= e(__('admin.languages.col_value', $displayName($editLocale))) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $effective = $row['override'] ?? $row['localeVal'] ?? $row['fallback'];
                            // Long strings (≥80 chars) get a textarea so newlines / quotes are easy to edit.
                            $useTextarea = mb_strlen((string) $effective) >= 80 || str_contains((string) $effective, "\n");
                        ?>
                            <tr class="<?= $row['override'] !== null ? 'lang-editor__row--overridden' : '' ?>">
                                <td class="lang-editor__col-key">
                                    <code><?= e($row['key']) ?></code>
                                    <?php if ($row['override'] !== null): ?>
                                        <span class="tag tag--soft"><?= e(__('admin.languages.tag_overridden')) ?></span>
                                    <?php elseif ($row['localeVal'] === null): ?>
                                        <span class="tag tag--soft"><?= e(__('admin.languages.tag_missing')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="lang-editor__col-default muted">
                                    <?php if ($editLocale !== 'ca' && $row['localeVal'] !== null && $row['localeVal'] !== $row['fallback']): ?>
                                        <em><?= e((string) $row['localeVal']) ?></em>
                                    <?php else: ?>
                                        <?= e((string) $row['fallback']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="lang-editor__col-value">
                                    <?php if ($useTextarea): ?>
                                        <textarea class="input lang-editor__input"
                                                  name="values[<?= e($row['key']) ?>]"
                                                  rows="2"><?= e((string) $effective) ?></textarea>
                                    <?php else: ?>
                                        <input class="input lang-editor__input" type="text"
                                               name="values[<?= e($row['key']) ?>]"
                                               value="<?= e((string) $effective) ?>">
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted lang-editor__hint"><?= e(__('admin.languages.editor_save_hint')) ?></p>
            <div><button type="submit" class="btn btn--primary"><?= e(__('admin.languages.editor_save')) ?></button></div>
        <?php endif; ?>
    </form>
</div>
