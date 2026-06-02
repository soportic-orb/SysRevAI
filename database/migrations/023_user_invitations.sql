-- ─────────────────────────────────────────────────────────────────────────────
-- 023_user_invitations.sql — Platform-level user invitations (admin-issued)
--
-- Lets an administrator invite somebody to join the platform itself by
-- sharing a single-use link. Distinct from `invitations` (which scopes
-- access to a specific review) — this one creates a brand-new user row
-- when accepted, with the role the admin chose.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}user_invitations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(190)    NOT NULL,
    `role`        ENUM('owner','admin','reviewer','viewer') NOT NULL DEFAULT 'reviewer',
    `token`       CHAR(64)        NOT NULL,
    `invited_by`  BIGINT UNSIGNED NULL,
    `expires_at`  TIMESTAMP       NULL DEFAULT NULL,
    `accepted_at` TIMESTAMP       NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_invitation_token` (`token`),
    KEY `idx_user_invitation_email` (`email`),
    CONSTRAINT `fk_user_invitation_inviter`
        FOREIGN KEY (`invited_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
