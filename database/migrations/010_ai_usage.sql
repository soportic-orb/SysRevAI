-- ─────────────────────────────────────────────────────────────────────────────
-- 010_ai_usage.sql — Claude API token/cost accounting (per review & feature)
--
-- Powers the admin usage counter and the monthly cost limit that disables AI
-- features when exceeded.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}ai_usage` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`     BIGINT UNSIGNED NULL,
    `user_id`       BIGINT UNSIGNED NULL,
    `feature`       VARCHAR(40)     NOT NULL,
    `model`         VARCHAR(60)     NOT NULL,
    `input_tokens`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `output_tokens` INT UNSIGNED    NOT NULL DEFAULT 0,
    `cost_usd`      DECIMAL(10,5)   NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aiusage_review` (`review_id`),
    KEY `idx_aiusage_created` (`created_at`),
    CONSTRAINT `fk_aiusage_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
