-- ─────────────────────────────────────────────────────────────────────────────
-- 015_full_text_retrieval.sql — Audit trail, consolidated status and job queue
--
-- Powers the cascade of academic APIs (Unpaywall, Europe PMC, PMC/NCBI,
-- OpenAlex, …) that try to obtain the full text of an imported reference.
-- The orchestrator + per-source classes live in src/Services/FullTextRetrieval.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}retrieval_attempts` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id`         BIGINT UNSIGNED NOT NULL,
    `source`               VARCHAR(50)     NOT NULL,
    `status`               ENUM('success','not_found','rate_limited','error','timeout') NOT NULL,
    `http_status`          INT UNSIGNED    NULL,
    `response_time_ms`     INT UNSIGNED    NULL,
    `pdf_found`            TINYINT(1)      NOT NULL DEFAULT 0,
    `pdf_url`              TEXT            NULL,
    `license_type`         VARCHAR(50)     NULL,
    `version_type`         VARCHAR(50)     NULL,
    `error_message`        TEXT            NULL,
    `raw_response_excerpt` TEXT            NULL,
    `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ra_reference` (`reference_id`),
    KEY `idx_ra_source_status` (`source`, `status`),
    KEY `idx_ra_created` (`created_at`),
    CONSTRAINT `fk_ra_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}reference_fulltext_status` (
    `reference_id`     BIGINT UNSIGNED NOT NULL,
    `has_fulltext`     TINYINT(1)      NOT NULL DEFAULT 0,
    `fulltext_source`  VARCHAR(50)     NULL,
    `fulltext_url`     TEXT            NULL,
    `license_type`     VARCHAR(50)     NULL,
    `version_type`     VARCHAR(50)     NULL,
    `last_attempt_at`  TIMESTAMP       NULL DEFAULT NULL,
    `next_retry_at`    TIMESTAMP       NULL DEFAULT NULL,
    `attempts_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `pdf_downloaded`   TINYINT(1)      NOT NULL DEFAULT 0,
    `pdf_local_path`   VARCHAR(500)    NULL,
    `xml_available`    TINYINT(1)      NOT NULL DEFAULT 0,
    `xml_local_path`   VARCHAR(500)    NULL,
    PRIMARY KEY (`reference_id`),
    KEY `idx_rfs_next_retry` (`next_retry_at`),
    CONSTRAINT `fk_rfs_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}retrieval_queue` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id`   BIGINT UNSIGNED NOT NULL,
    `priority`       TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `status`         ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `started_at`     TIMESTAMP       NULL DEFAULT NULL,
    `completed_at`   TIMESTAMP       NULL DEFAULT NULL,
    `error_message`  TEXT            NULL,
    `requested_by`   BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rq_status_priority` (`status`, `priority`),
    KEY `idx_rq_reference` (`reference_id`),
    CONSTRAINT `fk_rq_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rq_user`
        FOREIGN KEY (`requested_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
