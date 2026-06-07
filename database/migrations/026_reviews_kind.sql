-- ─────────────────────────────────────────────────────────────────────────────
-- 026_reviews_kind.sql — Differentiate systematic vs. scoping reviews.
--
-- A scoping review (PRISMA-ScR / JBI) maps the evidence rather than
-- aggregating it; it uses PCC (Population, Concept, Context) instead of
-- PICO and skips risk-of-bias appraisal entirely. The new column lets
-- one Reviews module host both kinds — the runtime swaps labels and
-- hides the RoB tab based on this flag.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}reviews`
    ADD COLUMN `kind` ENUM('systematic', 'scoping') NOT NULL DEFAULT 'systematic'
    AFTER `status`;
