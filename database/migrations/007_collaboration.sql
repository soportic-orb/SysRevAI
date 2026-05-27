-- ─────────────────────────────────────────────────────────────────────────────
-- 007_collaboration.sql — Multi-user collaboration
--
-- Invitations, in-app notifications, comments and per-article screening
-- assignments. `reference_id` columns carry no FK yet because the references
-- table arrives in Phase 5; they are indexed for when screening lands.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}invitations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`   BIGINT UNSIGNED NOT NULL,
    `email`       VARCHAR(190)    NOT NULL,
    `role`        ENUM('admin','reviewer','viewer') NOT NULL DEFAULT 'reviewer',
    `token`       CHAR(64)        NOT NULL,
    `invited_by`  BIGINT UNSIGNED NULL,
    `expires_at`  TIMESTAMP       NULL DEFAULT NULL,
    `accepted_at` TIMESTAMP       NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invitation_token` (`token`),
    KEY `idx_invitation_review` (`review_id`),
    KEY `idx_invitation_email` (`email`),
    CONSTRAINT `fk_invitation_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invitation_inviter`
        FOREIGN KEY (`invited_by`) REFERENCES `{prefix}users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}notifications` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`              BIGINT UNSIGNED NOT NULL,
    `type`                 VARCHAR(60)     NOT NULL,
    `title`                VARCHAR(255)    NOT NULL,
    `message`              TEXT            NULL,
    `action_url`           VARCHAR(255)    NULL,
    `is_read`              TINYINT(1)      NOT NULL DEFAULT 0,
    `related_review_id`    BIGINT UNSIGNED NULL,
    `related_reference_id` BIGINT UNSIGNED NULL,
    `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_read` (`user_id`, `is_read`),
    KEY `idx_notif_created` (`created_at`),
    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}comments` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`       BIGINT UNSIGNED NOT NULL,
    `reference_id`    BIGINT UNSIGNED NULL,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `parent_id`       BIGINT UNSIGNED NULL,
    `content`         TEXT            NOT NULL,
    `attachments_json` JSON           NULL,
    `mentions_json`   JSON            NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `edited_at`       TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_comment_review` (`review_id`),
    KEY `idx_comment_reference` (`reference_id`),
    KEY `idx_comment_parent` (`parent_id`),
    CONSTRAINT `fk_comment_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comment_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}screening_assignments` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`    BIGINT UNSIGNED NOT NULL,
    `reference_id` BIGINT UNSIGNED NULL,
    `reviewer_id`  BIGINT UNSIGNED NOT NULL,
    `stage`        ENUM('ta','ft') NOT NULL DEFAULT 'ta',
    `assigned_by`  BIGINT UNSIGNED NULL,
    `assigned_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_assign_review_reviewer` (`review_id`, `reviewer_id`),
    KEY `idx_assign_reference` (`reference_id`),
    CONSTRAINT `fk_assign_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_assign_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
