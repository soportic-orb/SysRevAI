-- ─────────────────────────────────────────────────────────────────────────────
-- 017_system_updates.sql — Log of OTA platform updates pulled from GitHub.
--
-- Used by Services\UpdateService (admin → Maintenance → Update). Each row
-- records the commit the system jumped from / to, who triggered it, and the
-- outcome of the migration run that followed.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}system_updates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_commit`    VARCHAR(64)  NULL,
    `to_commit`      VARCHAR(64)  NOT NULL,
    `triggered_by`   INT UNSIGNED NULL,
    `status`         ENUM('success','failed') NOT NULL,
    `migrations_run` INT UNSIGNED NOT NULL DEFAULT 0,
    `error`          TEXT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_su_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
