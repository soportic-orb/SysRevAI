-- ─────────────────────────────────────────────────────────────────────────────
-- 034_review_search_syntaxes.sql — Per-database search-strategy syntaxes.
--
-- The "Sintaxis de recerca" page (added in the review sub-nav) lets the
-- user record the verbatim search syntax they ran in each of the
-- supported bibliographic databases (PubMed / MEDLINE, CINAHL, Cochrane
-- Library, Scopus, Web of Science, ERIC, IEEE Xplore, ACM Digital
-- Library, APA PsycINFO). One row per (review, database, instance);
-- the page is re-saved as a bulk replace, so rows are wiped + re-
-- inserted on every Save.
--
-- FK type contract: reviews.id is BIGINT UNSIGNED (003_reviews.sql),
-- users.id is BIGINT UNSIGNED (001_users.sql).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE `{prefix}review_search_syntaxes` (
    `id`           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `review_id`    BIGINT UNSIGNED   NOT NULL,
    `database_key` VARCHAR(40)       NOT NULL,
    `syntax`       MEDIUMTEXT        NOT NULL,
    `sort_order`   INT UNSIGNED      NOT NULL DEFAULT 0,
    `updated_by`   BIGINT UNSIGNED   NULL,
    `created_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `rss_review_idx` (`review_id`, `sort_order`),
    CONSTRAINT `rss_review_fk` FOREIGN KEY (`review_id`)
        REFERENCES `{prefix}reviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `rss_user_fk` FOREIGN KEY (`updated_by`)
        REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
