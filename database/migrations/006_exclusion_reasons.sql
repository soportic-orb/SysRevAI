-- ─────────────────────────────────────────────────────────────────────────────
-- 006_exclusion_reasons.sql — Per-review, configurable exclusion reasons
--
-- `stage` indicates where a reason applies: title/abstract ('ta'), full text
-- ('ft') or both. Seeded from the admin "Reviews defaults" on review creation.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}exclusion_reasons` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`  BIGINT UNSIGNED NOT NULL,
    `label`      VARCHAR(190)    NOT NULL,
    `stage`      ENUM('ta','ft','both') NOT NULL DEFAULT 'both',
    `sort_order` INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_exclusion_review` (`review_id`),
    CONSTRAINT `fk_exclusion_review`
        FOREIGN KEY (`review_id`) REFERENCES `{prefix}reviews` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
