<?php

declare(strict_types=1);

/** @var string $type   'privacy' or 'terms' */
/** @var string $title */
/** @var array  $versions  language => ['use_default' => bool, 'custom_content' => ?string, 'last_updated' => string] */
/** @var string[] $languages */

$switchType = $type === 'privacy' ? 'terms' : 'privacy';
$switchUrl  = '/admin/legal/' . $switchType;
$switchLabel = $switchType === 'privacy'
    ? __('admin.legal.privacy_title')
    : __('admin.legal.terms_title');
?>
<h1 class="section__title"><?= e($title) ?></h1>

<p class="section__intro">
    <?= e(__('admin.legal.info_text')) ?>
</p>

<div class="section-card legal-admin__placeholders">
    <strong><?= e(__('admin.legal.placeholders_available')) ?>:</strong>
    <ul>
        <li><code>{{ADMIN_FULL_NAME}}</code></li>
        <li><code>{{ADMIN_EMAIL}}</code></li>
        <li><code>{{SITE_NAME}}</code></li>
        <li><code>{{SITE_URL}}</code></li>
        <li><code>{{LAST_UPDATED}}</code></li>
    </ul>
</div>

<div class="legal-admin__switch">
    <a class="btn btn--ghost btn--sm" href="<?= e($switchUrl) ?>">
        <?= e(__('admin.legal.switch_doc', $switchLabel)) ?> &rarr;
    </a>
    <a class="btn btn--ghost btn--sm" href="/<?= e($type) ?>" target="_blank" rel="noopener noreferrer">
        <?= e(__('admin.legal.preview', $type === 'privacy' ? '/privacy' : '/terms')) ?>
    </a>
</div>

<!-- Tabs (vanilla JS toggle below). The hash fragment lets us deep-link
     to a specific language after a save/restore redirect. -->
<div class="legal-admin">
    <div class="legal-admin__tabs" role="tablist">
        <?php foreach ($languages as $i => $lang): ?>
            <button type="button"
                    class="legal-admin__tab <?= $i === 0 ? 'is-active' : '' ?>"
                    role="tab"
                    data-lang-tab="<?= e($lang) ?>"
                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-controls="legal-panel-<?= e($lang) ?>">
                <?= e(__('admin.legal.lang_' . $lang)) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($languages as $i => $lang):
        $v = $versions[$lang] ?? ['use_default' => true, 'custom_content' => null, 'last_updated' => ''];
        $isCustom = !$v['use_default'];
    ?>
        <section class="legal-admin__panel <?= $i === 0 ? 'is-active' : '' ?>"
                 id="legal-panel-<?= e($lang) ?>"
                 role="tabpanel"
                 aria-labelledby="legal-tab-<?= e($lang) ?>"
                 data-lang-panel="<?= e($lang) ?>">

            <p class="legal-admin__badge-row">
                <?php if ($isCustom): ?>
                    <span class="tag tag--soft legal-admin__badge--custom"><?= e(__('admin.legal.using_custom')) ?></span>
                <?php else: ?>
                    <span class="tag tag--soft"><?= e(__('admin.legal.using_default')) ?></span>
                <?php endif; ?>
                <?php if ($v['last_updated'] !== ''): ?>
                    <span class="muted"><?= e(__('admin.legal.last_updated', $v['last_updated'])) ?></span>
                <?php endif; ?>
            </p>

            <form method="post" action="/admin/legal/<?= e($type) ?>/<?= e($lang) ?>" class="form-grid">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="content-<?= e($lang) ?>">
                        <?= e(__('admin.legal.custom_content_label')) ?>
                    </label>
                    <textarea class="input legal-editor"
                              id="content-<?= e($lang) ?>"
                              name="content"
                              rows="25"
                              spellcheck="false"
                              placeholder="<?= e(__('admin.legal.editor_placeholder')) ?>"><?= e((string) ($v['custom_content'] ?? '')) ?></textarea>
                    <span class="field-help"><?= e(__('admin.legal.editor_help')) ?></span>
                </div>
                <div>
                    <button type="submit" class="btn btn--primary"><?= e(__('admin.legal.save_custom')) ?></button>
                </div>
            </form>

            <?php if ($isCustom): ?>
                <form method="post"
                      action="/admin/legal/<?= e($type) ?>/<?= e($lang) ?>/restore"
                      class="legal-admin__restore"
                      data-confirm="<?= e(__('admin.legal.confirm_restore')) ?>"
                      data-confirm-button="<?= e(__('admin.legal.restore_default')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                        <?= e(__('admin.legal.restore_default')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
(function () {
    'use strict';
    var tabs   = document.querySelectorAll('[data-lang-tab]');
    var panels = document.querySelectorAll('[data-lang-panel]');
    if (!tabs.length) return;

    function activate(lang) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-lang-tab') === lang;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-lang-panel') === lang);
        });
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            var lang = t.getAttribute('data-lang-tab');
            activate(lang);
            history.replaceState(null, '', '#' + lang);
        });
    });

    // Open the language pointed to by the URL fragment (set by save/restore
    // redirects so the user lands back on the tab they were editing).
    var hash = (location.hash || '').replace('#', '');
    if (hash && document.querySelector('[data-lang-tab="' + hash + '"]')) {
        activate(hash);
    }
})();
</script>
