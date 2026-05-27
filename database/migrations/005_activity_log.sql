-- ─────────────────────────────────────────────────────────────────────────────
-- 005_activity_log.sql — Audit trail
--
-- Records security- and configuration-relevant actions. Sensitive values (e.g.
-- the content of an API key) are NEVER logged — only the action and the key name.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}activity_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NULL,
    `review_id`    BIGINT UNSIGNED NULL,
    `action`       VARCHAR(120)    NOT NULL,
    `details_json` JSON            NULL,
    `ip_address`   VARCHAR(45)     NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_user` (`user_id`),
    KEY `idx_activity_action` (`action`),
    KEY `idx_activity_created` (`created_at`),
    CONSTRAINT `fk_activity_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
