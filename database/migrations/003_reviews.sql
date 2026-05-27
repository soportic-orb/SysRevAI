-- ─────────────────────────────────────────────────────────────────────────────
-- 003_reviews.sql — Systematic reviews and their protocol
--
-- `screening_mode` controls the blinding strategy described in the
-- collaboration spec: double-blind (default), double-blind with auto third
-- reviewer, single reviewer, or pilot calibration.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}reviews` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`            BIGINT UNSIGNED NOT NULL,
    `title`               VARCHAR(255)    NOT NULL,
    `question`            TEXT            NULL,
    `pico_json`           JSON            NULL,
    `inclusion_criteria`  TEXT            NULL,
    `exclusion_criteria`  TEXT            NULL,
    `screening_mode`      ENUM('double_blind','double_blind_third','single','pilot') NOT NULL DEFAULT 'double_blind',
    `pilot_count`         INT UNSIGNED    NOT NULL DEFAULT 50,
    `reviewers_required`  TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `status`              ENUM('active','archived','completed') NOT NULL DEFAULT 'active',
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reviews_owner` (`owner_id`),
    KEY `idx_reviews_status` (`status`),
    CONSTRAINT `fk_reviews_owner`
        FOREIGN KEY (`owner_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
