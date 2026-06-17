-- ─────────────────────────────────────────────────────────────────────────────
-- 036_reviews_screening_guide.sql — Free-text screening guide.
--
-- Editable from "Editar protocol" and surfaced as a collapsible card
-- at the top of both screening boards (T/R and full-text). Lets the
-- review owner write a tailored decision rubric ("Inclou sempre si /
-- Exclou sempre si ...") so every reviewer applies the same lens to
-- every reference.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}reviews`
    ADD COLUMN `screening_guide` MEDIUMTEXT NULL AFTER `exclusion_criteria`;
