-- ─────────────────────────────────────────────────────────────────────────────
-- 002_settings.sql — Key/value runtime configuration (admin panel backed)
--
-- Sensitive values (API keys, SMTP passwords, service-account JSON) are stored
-- with `type` = 'encrypted' and the `value` column holds AES-256-GCM ciphertext.
-- Keys use dot notation, e.g. 'claude.api_key', 'site.name'.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}settings` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(190)    NOT NULL,
    `value`      LONGTEXT        NULL,
    `type`       ENUM('string','int','bool','json','encrypted') NOT NULL DEFAULT 'string',
    `group`      VARCHAR(64)     NOT NULL DEFAULT 'general',
    `is_public`  TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`),
    KEY `idx_settings_group` (`group`),
    CONSTRAINT `fk_settings_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
