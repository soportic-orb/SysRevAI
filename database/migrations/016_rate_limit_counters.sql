-- ─────────────────────────────────────────────────────────────────────────────
-- 016_rate_limit_counters.sql — Sliding-window counters per full-text source
--
-- Used by Services\FullTextRetrieval\RateLimiter to enforce per-source
-- request budgets even when the worker runs across separate cron ticks.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}rate_limit_counters` (
    `source`        VARCHAR(60)  NOT NULL,
    `window_start`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `count`         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`source`),
    KEY `idx_rlc_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
