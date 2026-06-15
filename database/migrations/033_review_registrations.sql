-- ─────────────────────────────────────────────────────────────────────────────
-- 033_review_registrations.sql — Per-review registration draft.
--
-- The Exports tab exposes a "Registre PROSPERO / OSF" page (PROSPERO for
-- systematic reviews, OSF preregistration for scoping reviews). The
-- field map for each kind is held in `data` as a JSON object keyed by
-- the field id defined in src/Services/RegistrationFields.php. The
-- user can fill the fields manually, prefill them with AI from the
-- existing protocol, then export to Word or PDF.
--
-- One row per review: a save() PUT overwrites the whole `data` blob
-- (the table is too small to bother with per-field rows, and the
-- payload comes off a single form submission).
--
-- FK type contract: reviews.id is BIGINT UNSIGNED (003_reviews.sql),
-- users.id is BIGINT UNSIGNED (001_users.sql). Both FK columns here
-- must match exactly or InnoDB rejects with errno 150.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE `{prefix}review_registrations` (
    `review_id`  BIGINT UNSIGNED                 NOT NULL,
    `kind`       ENUM('prospero','osf')          NOT NULL,
    `data`       JSON                            NOT NULL,
    `updated_by` BIGINT UNSIGNED                 NULL,
    `updated_at` TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`review_id`),
    CONSTRAINT `rr_review_fk` FOREIGN KEY (`review_id`)
        REFERENCES `{prefix}reviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `rr_user_fk` FOREIGN KEY (`updated_by`)
        REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
