-- ─────────────────────────────────────────────────────────────────────────────
-- 013_risk_of_bias.sql — Per-(reference, reviewer, tool, domain) judgements
--
-- Tools, domains and judgement vocabularies live in the application layer
-- (Services\RiskOfBiasService) so they can be extended without DB migrations.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}risk_of_bias` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id`  BIGINT UNSIGNED NOT NULL,
    `reviewer_id`   BIGINT UNSIGNED NOT NULL,
    `tool`          VARCHAR(40)     NOT NULL,
    `domain`        VARCHAR(60)     NOT NULL,
    `judgement`     VARCHAR(40)     NULL,
    `justification` TEXT            NULL,
    `ai_suggested`  TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rob_judgement` (`reference_id`, `reviewer_id`, `tool`, `domain`),
    KEY `idx_rob_review_tool` (`reference_id`, `tool`),
    CONSTRAINT `fk_rob_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rob_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
