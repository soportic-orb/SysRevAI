-- ─────────────────────────────────────────────────────────────────────────────
-- 037_reviews_duplicates_removed.sql — Persistent counter for the
-- "duplicates physically removed from the references list" so the
-- PRISMA flow diagram (Identified → Duplicates removed → Screened)
-- keeps reflecting the real number even after the user has bulk-
-- deleted the duplicate rows themselves.
--
-- Until now the diagram counted "currently in DB with status =
-- 'duplicate'", which fell to 0 the moment the user cleaned up
-- those rows from the references table. The counter below is
-- incremented by ReferencesController::deleteBulk / delete every
-- time a row with status='duplicate' is hard-deleted; the rest of
-- the workflow (status reads on the live table) stays unchanged.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}reviews`
    ADD COLUMN `duplicates_removed` INT UNSIGNED NOT NULL DEFAULT 0
        AFTER `screening_guide`;
