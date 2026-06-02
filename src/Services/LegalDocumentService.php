<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use InvalidArgumentException;
use RuntimeException;
use SysRevAI\Core\Database;

/**
 * Builds the public Privacy Policy and Terms of Use pages by rendering one
 * of two sources:
 *
 *   - the on-disk template at views/legal/templates/{type}.html, which
 *     ships three <section data-lang="es|ca|en"> blocks plus the placeholders
 *     {{ADMIN_FULL_NAME}}, {{ADMIN_EMAIL}}, {{SITE_NAME}}, {{SITE_URL}},
 *     {{LAST_UPDATED}};
 *
 *   - or, if the admin has saved a custom version per language, the HTML
 *     from the `legal_documents` table.
 *
 * Placeholders are substituted with `strtr()` after escaping each value, so
 * the resulting HTML is safe to echo directly into the public view.
 */
final class LegalDocumentService
{
    public const DOC_TYPES = ['privacy', 'terms'];
    public const SUPPORTED_LANGUAGES = ['es', 'ca', 'en'];
    public const DEFAULT_LANGUAGE = 'en';

    private string $templatesPath;
    /** @var array<string,string> */
    private array $cache = [];

    public function __construct(?string $templatesPath = null)
    {
        $this->templatesPath = $templatesPath ?? (string) config('paths.base') . '/views/legal/templates';
    }

    /**
     * Render the given document for the requested language, with all
     * placeholders substituted.
     */
    public function render(string $docType, string $language): string
    {
        $this->validateDocType($docType);
        $language = $this->normalizeLanguage($language);

        $key = "{$docType}:{$language}";
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $record = $this->getDocumentRecord($docType, $language);

        if (!$record['use_default'] && ($record['custom_content'] ?? '') !== '') {
            $html = $this->substitutePlaceholders((string) $record['custom_content']);
        } else {
            $html = $this->loadDefaultTemplate($docType, $language);
            $html = $this->substitutePlaceholders($html);
        }

        return $this->cache[$key] = $html;
    }

    /**
     * All three language rows for the admin editor.
     *
     * @return array<string,array{use_default:bool,custom_content:?string,last_updated:string}>
     */
    public function getAllVersions(string $docType): array
    {
        $this->validateDocType($docType);
        $table = Database::table('legal_documents');
        $rows = Database::select(
            "SELECT `language`, `use_default`, `custom_content`, `last_updated`
             FROM `{$table}` WHERE `doc_type` = ?",
            [$docType]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['language']] = [
                'use_default'    => (bool) $r['use_default'],
                'custom_content' => $r['custom_content'] !== null ? (string) $r['custom_content'] : null,
                'last_updated'   => (string) $r['last_updated'],
            ];
        }
        return $out;
    }

    public function saveCustomVersion(string $docType, string $language, string $htmlContent, int $userId): void
    {
        $this->validateDocType($docType);
        $language = $this->normalizeLanguage($language);

        $table = Database::table('legal_documents');
        Database::affecting(
            "UPDATE `{$table}`
                SET `use_default` = 0,
                    `custom_content` = ?,
                    `updated_by` = ?
              WHERE `doc_type` = ? AND `language` = ?",
            [$htmlContent, $userId, $docType, $language]
        );

        $this->invalidate($docType, $language);
    }

    public function restoreDefault(string $docType, string $language, int $userId): void
    {
        $this->validateDocType($docType);
        $language = $this->normalizeLanguage($language);

        $table = Database::table('legal_documents');
        Database::affecting(
            "UPDATE `{$table}`
                SET `use_default` = 1,
                    `custom_content` = NULL,
                    `updated_by` = ?
              WHERE `doc_type` = ? AND `language` = ?",
            [$userId, $docType, $language]
        );

        $this->invalidate($docType, $language);
    }

    /**
     * Render straight from the template file with the current placeholder
     * values. Used at install time, when the legal_documents table is not
     * seeded yet but the installer still needs to show the documents in a
     * modal for the admin to accept them.
     */
    public function renderTemplateOnly(string $docType, string $language, array $overrides = []): string
    {
        $this->validateDocType($docType);
        $language = $this->normalizeLanguage($language);
        $html = $this->loadDefaultTemplate($docType, $language);
        return $this->substitutePlaceholders($html, $overrides);
    }

    /* ── internals ──────────────────────────────────────────────────────── */

    private function substitutePlaceholders(string $html, array $overrides = []): string
    {
        $values = array_merge($this->placeholderValues(), $overrides);
        return strtr($html, $values);
    }

    /** @return array<string,string> */
    private function placeholderValues(): array
    {
        $users = Database::table('users');
        $admin = Database::selectOne(
            "SELECT `name`, `email` FROM `{$users}`
              WHERE `role` IN ('owner', 'admin') AND `is_active` = 1
              ORDER BY CASE `role` WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 END, `id` ASC
              LIMIT 1"
        );
        $adminName  = (string) ($admin['name']  ?? '');
        $adminEmail = (string) ($admin['email'] ?? '');

        $siteName = (string) (setting('site.name')
            ?? setting('app.name')
            ?? config('app.name', 'SysRevAI'));
        $siteUrl  = (string) (setting('site.url')
            ?? setting('app.url')
            ?? config('app.url', ''));

        $esc = static fn (string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return [
            '{{ADMIN_FULL_NAME}}' => $esc($adminName),
            '{{ADMIN_EMAIL}}'     => $esc($adminEmail),
            '{{SITE_NAME}}'       => $esc($siteName),
            '{{SITE_URL}}'        => $esc($siteUrl),
            '{{LAST_UPDATED}}'    => (new \DateTimeImmutable('now'))->format('Y-m-d'),
        ];
    }

    private function loadDefaultTemplate(string $docType, string $language): string
    {
        $path = $this->templatesPath . '/' . $docType . '.html';
        if (!is_readable($path)) {
            throw new RuntimeException("Legal template not found: {$path}");
        }
        $full = (string) file_get_contents($path);

        $section = $this->extractLanguageSection($full, $language)
            ?? $this->extractLanguageSection($full, self::DEFAULT_LANGUAGE);

        if ($section === null) {
            throw new RuntimeException(
                "No language section in {$docType} template (requested: {$language})"
            );
        }
        return $section;
    }

    private function extractLanguageSection(string $html, string $language): ?string
    {
        $pattern = '#<section[^>]*data-lang="' . preg_quote($language, '#') . '"[^>]*>(.*?)</section>#si';
        if (preg_match($pattern, $html, $m) !== 1) {
            return null;
        }
        $langAttr = htmlspecialchars($language, ENT_QUOTES, 'UTF-8');
        return '<section class="legal-doc" lang="' . $langAttr . '">' . $m[1] . '</section>';
    }

    /** @return array{use_default:bool,custom_content:?string} */
    private function getDocumentRecord(string $docType, string $language): array
    {
        $table = Database::table('legal_documents');
        $row = Database::selectOne(
            "SELECT `use_default`, `custom_content`
               FROM `{$table}`
              WHERE `doc_type` = ? AND `language` = ? LIMIT 1",
            [$docType, $language]
        );
        if ($row === null) {
            // Seed missing — fall back to the on-disk template, don't crash.
            return ['use_default' => true, 'custom_content' => null];
        }
        return [
            'use_default'    => (bool) $row['use_default'],
            'custom_content' => $row['custom_content'] !== null ? (string) $row['custom_content'] : null,
        ];
    }

    private function validateDocType(string $docType): void
    {
        if (!in_array($docType, self::DOC_TYPES, true)) {
            throw new InvalidArgumentException("Invalid legal document type: {$docType}");
        }
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(substr($language, 0, 2));
        return in_array($language, self::SUPPORTED_LANGUAGES, true)
            ? $language
            : self::DEFAULT_LANGUAGE;
    }

    private function invalidate(string $docType, string $language): void
    {
        unset($this->cache["{$docType}:{$language}"]);
    }
}
