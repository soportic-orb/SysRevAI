-- ─────────────────────────────────────────────────────────────────────────────
-- 041_references_publication_type_date.sql — Publication type/date columns.
--
-- Adds two columns populated at import time (ImportService normalises the
-- raw RIS `TY`/`DA`, BibTeX entry type/`month`, PubMed XML
-- PublicationType/PubDate, and EndNote ref-type into these) so the
-- references list can show a "publication type" pill next to the source
-- pill and filter/sort by publication date:
--
--   • publication_type — short canonical label (e.g. "Journal Article",
--     "Conference Paper", "Book Chapter", "Thesis", "Report", "Review",
--     "Other"). NULL for references imported before this migration or
--     whose source format didn't carry a recognisable type.
--   • publication_date — day-precision date when the source format gave
--     one (RIS `DA`, PubMed XML day/month); otherwise NULL. The existing
--     `year` column remains the fallback for the date-range filter so
--     older/year-only references are still matched by year.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}references`
    ADD COLUMN `publication_type` VARCHAR(64) NULL AFTER `year`,
    ADD COLUMN `publication_date` DATE         NULL AFTER `publication_type`,
    ADD KEY `idx_ref_pubtype` (`review_id`, `publication_type`),
    ADD KEY `idx_ref_pubdate` (`review_id`, `publication_date`);
