-- ─────────────────────────────────────────────────────────────────────────────
-- 039_reviews_prisma_overrides.sql — Manual overrides for the PRISMA flow.
--
-- ExportService::prismaCounts() computes each cell from the live data
-- (references table + import_logs + duplicates_removed counter). The
-- column added here lets the user (or the Copilot, via the new
-- set_prisma_cells action) OVERRIDE any of those cells with an
-- absolute value — useful when:
--
--   • the user de-duplicated outside the platform and just wants the
--     "Records identified" number to match the manuscript;
--   • the workflow doesn't perfectly map to PRISMA 2020 cells and the
--     user wants the final figure to reflect what really happened;
--   • some references were excluded for a reason that doesn't have a
--     workflow status (eg. wrong language) and the user wants the
--     "Excluded" cell to count them too.
--
-- Shape: JSON object keyed by cell name. NULL or missing key → use
-- the computed value. Empty string treated the same way. Allowed
-- keys mirror what prismaCounts() returns:
--   identified, duplicates, after_dedup, screened_ta, excluded_ta,
--   sought_retrieval, assessed_ft, excluded_ft, included
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}reviews`
    ADD COLUMN `prisma_overrides` JSON NULL AFTER `duplicates_removed`;
