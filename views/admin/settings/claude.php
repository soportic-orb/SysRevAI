<?php

declare(strict_types=1);

use SysRevAI\Core\Config;

$hasKey   = Config::hasEncrypted('claude.api_key');
$complex  = (string) (setting('claude.model_complex') ?? 'claude-opus-4-7');
$light    = (string) (setting('claude.model_light') ?? 'claude-haiku-4-5-20251001');
$maxTok   = (int) (setting('claude.max_tokens') ?? 4096);
$limit    = (int) (setting('claude.monthly_limit_usd') ?? 0);

$models = [
    'claude-opus-4-7'            => 'Claude Opus 4.7',
    'claude-sonnet-4-6'          => 'Claude Sonnet 4.6',
    'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
];
$features = ['summaries', 'screening', 'extraction', 'bias', 'chat', 'dedup'];
?>
<h1 class="section__title"><?= e(__('admin.sections.claude')) ?></h1>
<p class="section__intro"><?= e(__('admin.claude.intro')) ?></p>

<form method="post" action="/admin/settings/claude" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field-label" for="api_key"><?= e(__('admin.claude.api_key')) ?></label>
        <input class="input" id="api_key" name="api_key" type="password" autocomplete="off"
               placeholder="<?= $hasKey ? 'sk-ant-••••••••••••  (' . e(__('admin.claude.key_set')) . ')' : 'sk-ant-...' ?>">
        <span class="field-help"><?= e(__('admin.claude.api_key_help')) ?></span>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="model_complex"><?= e(__('admin.claude.model_complex')) ?></label>
            <select class="select" id="model_complex" name="model_complex">
                <?php foreach ($models as $id => $name): ?>
                    <option value="<?= e($id) ?>" <?= $complex === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="model_light"><?= e(__('admin.claude.model_light')) ?></label>
            <select class="select" id="model_light" name="model_light">
                <?php foreach ($models as $id => $name): ?>
                    <option value="<?= e($id) ?>" <?= $light === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="max_tokens"><?= e(__('admin.claude.max_tokens')) ?></label>
        <input class="input" id="max_tokens" name="max_tokens" type="number" min="1" value="<?= $maxTok ?>">
    </div>

    <div class="field">
        <label class="field-label" for="monthly_limit_usd"><?= e(__('admin.claude.monthly_limit')) ?></label>
        <input class="input" id="monthly_limit_usd" name="monthly_limit_usd" type="number" min="0" value="<?= $limit ?>">
        <span class="field-help"><?= e(__('admin.claude.monthly_limit_help')) ?></span>
    </div>

    <fieldset class="toggles">
        <legend><?= e(__('admin.claude.features')) ?></legend>
        <?php foreach ($features as $f): ?>
            <label class="checkbox">
                <input type="checkbox" name="feature[<?= e($f) ?>]" value="1"
                       <?= (bool) (setting('claude.feature.' . $f) ?? true) ? 'checked' : '' ?>>
                <?= e(__('admin.claude.feature_' . $f)) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <div class="actions actions--start">
        <button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button>
    </div>
</form>

<form method="post" action="/admin/settings/claude/verify" class="section-card section-card--inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn--ghost"><?= e(__('admin.claude.verify')) ?></button>
    <a class="link-ext" href="https://console.anthropic.com/" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>
</form>

<?php
    try { $monthCost = \SysRevAI\Models\AiUsage::monthlyCost(); }
    catch (\Throwable) { $monthCost = 0.0; }
?>
<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.claude.usage_title')) ?></h2>
    <p class="section__intro">
        <?= e(__('admin.claude.usage_month')) ?>: <strong>$<?= e(number_format($monthCost, 2)) ?></strong>
        <?php $lim = (int) (setting('claude.monthly_limit_usd') ?? 0); if ($lim > 0): ?>
            / $<?= $lim ?>
        <?php endif; ?>
    </p>
</div>
