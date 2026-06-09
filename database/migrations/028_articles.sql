-- ─────────────────────────────────────────────────────────────────────────────
-- 028_articles.sql — Per-article analysis projects.
--
-- The Article workspace gives a researcher a focused project around one
-- already-written paper: upload PDF / DOCX, the platform extracts the text,
-- AI chat is grounded in that text, and a critical report can be generated.
--
-- One owner per article; collaboration is the same shape as a review —
-- a join table for members, a token-based invitation table.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}articles` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`        BIGINT UNSIGNED NOT NULL,
    `title`           VARCHAR(512)    NOT NULL,
    `source_filename` VARCHAR(512)    NULL,
    `mime`            VARCHAR(80)     NULL,
    `file_path`       VARCHAR(1024)   NULL,
    `extracted_text`  MEDIUMTEXT      NULL,
    `char_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_articles_owner` (`owner_id`),
    CONSTRAINT `fk_articles_owner`
        FOREIGN KEY (`owner_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}article_users` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `role`         VARCHAR(32)     NOT NULL DEFAULT 'collaborator',
    `joined_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `removed_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_user` (`article_id`, `user_id`),
    KEY `idx_au_article` (`article_id`),
    KEY `idx_au_user`    (`user_id`),
    CONSTRAINT `fk_au_article`
        FOREIGN KEY (`article_id`) REFERENCES `{prefix}articles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_au_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}article_invitations` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id`   BIGINT UNSIGNED NOT NULL,
    `email`        VARCHAR(255)    NOT NULL,
    `role`         VARCHAR(32)     NOT NULL DEFAULT 'collaborator',
    `token`        CHAR(64)        NOT NULL,
    `invited_by`   BIGINT UNSIGNED NULL,
    `accepted_at`  TIMESTAMP       NULL DEFAULT NULL,
    `expires_at`   TIMESTAMP       NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invite_token` (`token`),
    KEY `idx_ai_article` (`article_id`),
    KEY `idx_ai_email`   (`email`),
    CONSTRAINT `fk_ai_article`
        FOREIGN KEY (`article_id`) REFERENCES `{prefix}articles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
