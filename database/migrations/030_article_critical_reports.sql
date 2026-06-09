-- ─────────────────────────────────────────────────────────────────────────────
-- 030_article_critical_reports.sql — AI-generated critical reports for the
-- Article analysis tool. One report per article (latest wins on regen).
--
-- The report is a JSON blob with five 0-100 axes (methodology / clarity /
-- novelty / evidence / limitations), a one-paragraph executive summary, a
-- mandatory devil's-advocate counter-argument, an overall verdict and an
-- ordered list of section-by-section recommendations the user can act on.
-- Structured by ClaudeService::articleCriticalReport, rendered by the
-- articles/critical_report view.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}article_critical_reports` (
    `article_id`   BIGINT UNSIGNED NOT NULL,
    `data_json`    MEDIUMTEXT      NOT NULL,
    `model`        VARCHAR(80)     NULL,
    `generated_by` BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`article_id`),
    CONSTRAINT `fk_article_critical_article`
        FOREIGN KEY (`article_id`) REFERENCES `{prefix}articles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
