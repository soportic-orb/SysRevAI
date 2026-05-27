-- ─────────────────────────────────────────────────────────────────────────────
-- 008_references.sql — Imported references, import logs and duplicate pairs
--
-- The prefixed table name (e.g. `sra_references`) avoids the MySQL reserved
-- word `references`. FULLTEXT index supports the search feature.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}references` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`     BIGINT UNSIGNED NOT NULL,
    `title`         TEXT            NULL,
    `authors_json`  JSON            NULL,
    `year`          SMALLINT UNSIGNED NULL,
    `journal`       VARCHAR(512)    NULL,
    `abstract`      MEDIUMTEXT      NULL,
    `doi`           VARCHAR(255)    NULL,
    `pmid`          VARCHAR(32)     NULL,
    `url`           VARCHAR(1024)   NULL,
    `keywords_json` JSON            NULL,
    `source_file`   VARCHAR(255)    NULL,
    `dedup_key`     VARCHAR(255)    NULL,
    `status`        ENUM('imported','duplicate','ta_screening','ta_included','ta_excluded','ft_screening','ft_included','ft_excluded','extracted')
                    NOT NULL DEFAULT 'imported',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ref_review` (`review_id`),
    KEY `idx_ref_status` (`review_id`, `status`),
    KEY `idx_ref_doi` (`doi`),
    KEY `idx_ref_pmid` (`pmid`),
    KEY `idx_ref_dedup` (`review_id`, `dedup_key`),
    FULLTEXT KEY `ft_ref` (`title`, `abstract`),
    CONSTRAINT `fk_ref_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}import_logs` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`        BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NULL,
    `filename`         VARCHAR(255)    NULL,
    `format`           VARCHAR(20)     NULL,
    `total_parsed`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_imported`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_duplicates` INT UNSIGNED    NOT NULL DEFAULT 0,
    `errors`           TEXT            NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_importlog_review` (`review_id`),
    CONSTRAINT `fk_importlog_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_importlog_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}duplicates` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`  BIGINT UNSIGNED NOT NULL,
    `ref_a_id`   BIGINT UNSIGNED NOT NULL,
    `ref_b_id`   BIGINT UNSIGNED NOT NULL,
    `method`     ENUM('doi','pmid','title','fuzzy','semantic') NOT NULL,
    `confidence` DECIMAL(4,3)    NOT NULL DEFAULT 1.000,
    `status`     ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
    `reason`     VARCHAR(512)    NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dup_review_status` (`review_id`, `status`),
    CONSTRAINT `fk_dup_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dup_ref_a`
        FOREIGN KEY (`ref_a_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dup_ref_b`
        FOREIGN KEY (`ref_b_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
