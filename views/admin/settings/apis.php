<?php

declare(strict_types=1);

use SysRevAI\Core\Config;
?>
<h1 class="section__title"><?= e(__('admin.sections.apis')) ?></h1>
<p class="section__intro"><?= e(__('admin.apis.intro')) ?></p>

<form method="post" action="/admin/settings/apis" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="api-block">
        <h2 class="section__subtitle">PubMed (E-utilities)</h2>
        <p class="api-help">
            <?= e(__('admin.apis.help_pubmed')) ?>
            <a href="https://www.ncbi.nlm.nih.gov/account/settings/" target="_blank" rel="noopener noreferrer">
                <?= e(__('admin.apis.link_pubmed')) ?> &rarr;
            </a>
        </p>
        <div class="field">
            <label class="field-label" for="pubmed_api_key"><?= e(__('admin.apis.api_key_optional')) ?></label>
            <input class="input" id="pubmed_api_key" name="pubmed_api_key" type="password" autocomplete="off"
                   placeholder="<?= Config::hasEncrypted('pubmed.api_key') ? '••••••  (' . e(__('admin.claude.key_set')) . ')' : '' ?>">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="pubmed_enabled" value="1" <?= (bool) (setting('pubmed.enabled') ?? false) ? 'checked' : '' ?>>
            <?= e(__('admin.apis.enable')) ?>
        </label>
    </div>

    <div class="api-block">
        <h2 class="section__subtitle">CrossRef</h2>
        <p class="api-help">
            <?= e(__('admin.apis.help_crossref')) ?>
            <a href="https://www.crossref.org/documentation/retrieve-metadata/rest-api/tips-for-using-the-crossref-rest-api/" target="_blank" rel="noopener noreferrer">
                <?= e(__('admin.apis.link_crossref')) ?> &rarr;
            </a>
        </p>
        <div class="field">
            <label class="field-label" for="crossref_email"><?= e(__('admin.apis.polite_email')) ?></label>
            <input class="input" id="crossref_email" name="crossref_email" type="email" value="<?= e((string) (setting('crossref.email') ?? '')) ?>">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="crossref_enabled" value="1" <?= (bool) (setting('crossref.enabled') ?? false) ? 'checked' : '' ?>>
            <?= e(__('admin.apis.enable')) ?>
        </label>
    </div>

    <div class="api-block">
        <h2 class="section__subtitle">Unpaywall</h2>
        <p class="api-help">
            <?= e(__('admin.apis.help_unpaywall')) ?>
            <a href="https://unpaywall.org/products/api" target="_blank" rel="noopener noreferrer">
                <?= e(__('admin.apis.link_unpaywall')) ?> &rarr;
            </a>
        </p>
        <div class="field">
            <label class="field-label" for="unpaywall_email"><?= e(__('admin.apis.required_email')) ?></label>
            <input class="input" id="unpaywall_email" name="unpaywall_email" type="email" value="<?= e((string) (setting('unpaywall.email') ?? '')) ?>">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="unpaywall_enabled" value="1" <?= (bool) (setting('unpaywall.enabled') ?? false) ? 'checked' : '' ?>>
            <?= e(__('admin.apis.enable')) ?>
        </label>
    </div>

    <div class="api-block">
        <h2 class="section__subtitle">OpenAlex</h2>
        <p class="api-help">
            <?= e(__('admin.apis.help_openalex')) ?>
            <a href="https://docs.openalex.org/how-to-use-the-api/rate-limits-and-authentication" target="_blank" rel="noopener noreferrer">
                <?= e(__('admin.apis.link_openalex')) ?> &rarr;
            </a>
        </p>
        <label class="checkbox">
            <input type="checkbox" name="openalex_enabled" value="1" <?= (bool) (setting('openalex.enabled') ?? false) ? 'checked' : '' ?>>
            <?= e(__('admin.apis.enable')) ?>
        </label>
    </div>

    <div class="api-block">
        <h2 class="section__subtitle">Semantic Scholar</h2>
        <p class="api-help">
            <?= e(__('admin.apis.help_semantic_scholar')) ?>
            <a href="https://www.semanticscholar.org/product/api#api-key-form" target="_blank" rel="noopener noreferrer">
                <?= e(__('admin.apis.link_semantic_scholar')) ?> &rarr;
            </a>
        </p>
        <div class="field">
            <label class="field-label" for="semantic_scholar_api_key"><?= e(__('admin.apis.api_key_optional')) ?></label>
            <input class="input" id="semantic_scholar_api_key" name="semantic_scholar_api_key" type="password" autocomplete="off"
                   placeholder="<?= Config::hasEncrypted('semantic_scholar.api_key') ? '••••••  (' . e(__('admin.claude.key_set')) . ')' : '' ?>">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="semantic_scholar_enabled" value="1" <?= (bool) (setting('semantic_scholar.enabled') ?? false) ? 'checked' : '' ?>>
            <?= e(__('admin.apis.enable')) ?>
        </label>
    </div>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
