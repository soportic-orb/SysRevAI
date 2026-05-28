-- ─────────────────────────────────────────────────────────────────────────────
-- 014_summaries_translations.sql — AI summaries & cached Google translations
--
-- The summary JSON has background/methods/results/conclusions/relevance keys.
-- Translations are keyed by SHA-256 of the source text + lang pair so chunked
-- documents can be reassembled from cache without re-hitting the API.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}ai_summaries` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `language`     VARCHAR(8)      NOT NULL DEFAULT 'ca',
    `summary_json` JSON            NOT NULL,
    `model_used`   VARCHAR(60)     NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_summary_lang` (`reference_id`, `language`),
    CONSTRAINT `fk_summary_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}translations` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_hash`     CHAR(64)        NOT NULL,
    `source_lang`     VARCHAR(8)      NOT NULL DEFAULT 'auto',
    `target_lang`     VARCHAR(8)      NOT NULL,
    `translated_text` MEDIUMTEXT      NOT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_translation` (`source_hash`, `source_lang`, `target_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
