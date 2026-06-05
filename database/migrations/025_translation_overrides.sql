-- ─────────────────────────────────────────────────────────────────────────────
-- 025_translation_overrides.sql — Per-locale UI-string overrides.
--
-- The platform ships with file-based translations (/lang/{locale}.php) as the
-- defaults. This table lets admins overwrite any key for any locale at runtime
-- without touching the codebase, and is also how custom locales gain content.
--
-- One row per (locale, key_path). The lookup layer in I18n loads all rows for
-- the active locale once per request and applies them as overlays on top of
-- the file-based map; deleting a row restores the platform default.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}translation_overrides` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `locale`     VARCHAR(8)      NOT NULL,
    `key_path`   VARCHAR(255)    NOT NULL,
    `value`      MEDIUMTEXT      NOT NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_locale_key` (`locale`, `key_path`),
    KEY `idx_locale` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
