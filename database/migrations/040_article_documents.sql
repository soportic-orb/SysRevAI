-- ─────────────────────────────────────────────────────────────────────────────
-- 040_article_documents.sql — Secondary documents attached to an article.
--
-- The primary manuscript uploaded when the article is created stays
-- on the articles row (extracted_text + file_path). The new table
-- below holds ANY number of additional reference materials the user
-- wants the Copilot to know about — companion papers for comparison,
-- methodology references, related guidelines, etc.
--
-- extracted_text is the plain-text dump produced at upload time by
-- DocumentTextExtractor; the Copilot reads truncated chunks of these
-- as part of its system prompt so the user can ask questions like
-- "compare the methodology of the main paper to this guideline".
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE `{prefix}article_documents` (
    `id`             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `article_id`     BIGINT UNSIGNED   NOT NULL,
    `filename`       VARCHAR(512)      NOT NULL,
    `mime`           VARCHAR(80)       NULL,
    `file_path`      VARCHAR(1024)     NOT NULL,
    `extracted_text` MEDIUMTEXT        NULL,
    `char_count`     INT UNSIGNED      NOT NULL DEFAULT 0,
    `uploaded_by`    BIGINT UNSIGNED   NULL,
    `created_at`     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ad_article_idx` (`article_id`, `created_at`),
    CONSTRAINT `ad_article_fk` FOREIGN KEY (`article_id`)
        REFERENCES `{prefix}articles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ad_user_fk` FOREIGN KEY (`uploaded_by`)
        REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
