-- ─────────────────────────────────────────────────────────────────────────────
-- 009_screening_decisions.sql — Reviewer decisions for title/abstract & full text
--
-- Blinding is enforced in the application layer: a reviewer never reads another
-- reviewer's row until everyone required has decided. `is_resolution` marks a
-- coordinator's final adjudication of a conflict.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}screening_decisions` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_id`          BIGINT UNSIGNED NOT NULL,
    `reviewer_id`           BIGINT UNSIGNED NOT NULL,
    `stage`                 ENUM('ta','ft') NOT NULL DEFAULT 'ta',
    `decision`              ENUM('include','exclude','maybe') NOT NULL,
    `reason`                VARCHAR(255)    NULL,
    `notes`                 TEXT            NULL,
    `is_resolution`         TINYINT(1)      NOT NULL DEFAULT 0,
    `resolved_decisions_ids` JSON           NULL,
    `time_spent_seconds`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_decision` (`reference_id`, `reviewer_id`, `stage`, `is_resolution`),
    KEY `idx_decision_reviewer` (`reviewer_id`, `stage`),
    CONSTRAINT `fk_decision_reference`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_decision_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `{prefix}users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
