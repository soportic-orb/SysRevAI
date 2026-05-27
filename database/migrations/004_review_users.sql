-- ─────────────────────────────────────────────────────────────────────────────
-- 004_review_users.sql — Many-to-many: which users collaborate on a review
--
-- `is_blinded` enforces the methodological blinding during screening;
-- `can_resolve_conflicts` flags reviewers allowed to adjudicate disagreements.
-- `removed_at` is a soft-delete so a member's history is preserved.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}review_users` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`             BIGINT UNSIGNED NOT NULL,
    `user_id`               BIGINT UNSIGNED NOT NULL,
    `role`                  ENUM('owner','admin','reviewer','viewer') NOT NULL DEFAULT 'reviewer',
    `is_blinded`            TINYINT(1)      NOT NULL DEFAULT 1,
    `can_resolve_conflicts` TINYINT(1)      NOT NULL DEFAULT 0,
    `joined_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `removed_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_review_user` (`review_id`, `user_id`),
    KEY `idx_review_users_user` (`user_id`),
    CONSTRAINT `fk_review_users_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_review_users_user`
        FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
