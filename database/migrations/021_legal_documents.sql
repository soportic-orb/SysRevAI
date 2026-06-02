-- ─────────────────────────────────────────────────────────────────────────────
-- 021_legal_documents.sql — Per-(doc_type, language) editable legal documents.
--
-- Each row says whether the public /privacy or /terms page should fall back to
-- the on-disk template (use_default = 1, custom_content NULL) or render an
-- admin-edited version. The placeholder system is applied either way.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}legal_documents` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doc_type`        ENUM('privacy', 'terms') NOT NULL,
    `language`        CHAR(2) NOT NULL,
    `use_default`     TINYINT(1) NOT NULL DEFAULT 1,
    `custom_content`  LONGTEXT NULL,
    `last_updated`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`      BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_legal_doc_lang` (`doc_type`, `language`),
    CONSTRAINT `fk_legal_doc_user`
        FOREIGN KEY (`updated_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per (doc_type, language), all starting out as "use default template".
INSERT INTO `{prefix}legal_documents` (`doc_type`, `language`, `use_default`) VALUES
    ('privacy', 'es', 1),
    ('privacy', 'ca', 1),
    ('privacy', 'en', 1),
    ('terms',   'es', 1),
    ('terms',   'ca', 1),
    ('terms',   'en', 1);
