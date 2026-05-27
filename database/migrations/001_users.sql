-- ─────────────────────────────────────────────────────────────────────────────
-- 001_users.sql — Accounts and authentication
--
-- The `{prefix}` token is replaced with the configured table prefix (default
-- `sra_`) by the migration runner before execution. Charset utf8mb4, InnoDB.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}users` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                   VARCHAR(190)    NOT NULL,
    `email`                  VARCHAR(190)    NOT NULL,
    `password_hash`          VARCHAR(255)    NOT NULL,
    `role`                   ENUM('owner','admin','reviewer','viewer') NOT NULL DEFAULT 'reviewer',
    `status`                 ENUM('active','pending','suspended')      NOT NULL DEFAULT 'active',
    `locale`                 VARCHAR(5)      NOT NULL DEFAULT 'ca',
    `is_active`              TINYINT(1)      NOT NULL DEFAULT 1,
    `two_factor_secret`      TEXT            NULL,            -- AES-256-GCM encrypted
    `notification_preferences` JSON          NULL,
    `failed_login_attempts`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `locked_until`           TIMESTAMP       NULL DEFAULT NULL,
    `email_verified_at`      TIMESTAMP       NULL DEFAULT NULL,
    `last_login_at`          TIMESTAMP       NULL DEFAULT NULL,
    `created_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
