-- ─────────────────────────────────────────────────────────────────────────────
-- 012_extraction.sql — Data-extraction templates and per-reviewer submissions
--
-- `fields_json` stores the template schema (an array of fields with key/label/
-- type/options/required). `data_json` stores the reviewer's filled values keyed
-- by field key.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}extraction_templates` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`   BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(190)    NOT NULL,
    `fields_json` JSON            NOT NULL,
    `is_default`  TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_template_review` (`review_id`),
    CONSTRAINT `fk_template_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}extraction_data` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `reviewer_id`  BIGINT UNSIGNED NOT NULL,
    `template_id`  BIGINT UNSIGNED NOT NULL,
    `data_json`    JSON            NOT NULL,
    `status`       ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    `approved_by`  BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_extraction` (`reference_id`, `reviewer_id`, `template_id`),
    KEY `idx_extraction_reference` (`reference_id`),
    CONSTRAINT `fk_extraction_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_extraction_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_extraction_template`
        FOREIGN KEY (`template_id`) REFERENCES `{prefix}extraction_templates` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
