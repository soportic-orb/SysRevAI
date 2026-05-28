-- ─────────────────────────────────────────────────────────────────────────────
-- 011_full_text.sql — Per-reference full-text PDFs and per-article chat history
--
-- PDFs themselves live on disk (outside the docroot) under a UUID filename;
-- this table records the path, original name, page count and extracted text
-- (FULLTEXT-indexed) for search and AI workflows.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}reference_full_text` (
    `reference_id`      BIGINT UNSIGNED NOT NULL,
    `pdf_path`          VARCHAR(512)    NOT NULL,
    `original_filename` VARCHAR(255)    NULL,
    `extracted_text`    LONGTEXT        NULL,
    `page_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `size_bytes`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `uploaded_by`       BIGINT UNSIGNED NULL,
    `uploaded_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reference_id`),
    FULLTEXT KEY `ft_pdf_text` (`extracted_text`),
    CONSTRAINT `fk_pdf_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pdf_uploader`
        FOREIGN KEY (`uploaded_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}ai_chat_history` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `role`         ENUM('user','assistant') NOT NULL,
    `content`      MEDIUMTEXT      NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_ref_user` (`reference_id`, `user_id`, `id`),
    CONSTRAINT `fk_chat_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_chat_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
