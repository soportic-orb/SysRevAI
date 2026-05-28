-- ─────────────────────────────────────────────────────────────────────────────
-- 018_copilot_messages.sql — Persistent Scientific Copilot transcript.
--
-- One row per turn (user or assistant), scoped per (review, user) so each
-- researcher has their own thread inside a review and the model can reference
-- earlier turns across devices / sessions. Stored server-side so the user
-- doesn't lose history when they clear browser data or move to another machine.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}copilot_messages` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`  INT UNSIGNED    NOT NULL,
    `user_id`    INT UNSIGNED    NOT NULL,
    `role`       ENUM('user','assistant') NOT NULL,
    `content`    MEDIUMTEXT      NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cm_thread` (`review_id`, `user_id`, `id`),
    KEY `idx_cm_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
