-- ─────────────────────────────────────────────────────────────────────────────
-- 035_review_protocol_file.sql — Persist the protocol document.
--
-- The "Editar protocol" page (and the AI-upload widget on the new-review
-- form) lets the user upload a PDF / DOCX so Claude can extract the
-- PICO + criteria. Until now, the bytes were thrown away after
-- extraction. Now we keep the original file on disk under
-- storage/protocols/ and reference it from the reviews row so it can
-- be downloaded back from the protocol page.
--
-- Columns added (all NULL — historical reviews simply don't have a
-- file, the download button is hidden in that case):
--   protocol_path         absolute disk path under storage/protocols/
--   protocol_filename     original user-visible filename (UTF-8)
--   protocol_mime         finfo-detected MIME (pdf or docx)
--   protocol_uploaded_at  when the upload landed
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}reviews`
    ADD COLUMN `protocol_path`        VARCHAR(1024) NULL AFTER `exclusion_criteria`,
    ADD COLUMN `protocol_filename`    VARCHAR(512)  NULL AFTER `protocol_path`,
    ADD COLUMN `protocol_mime`        VARCHAR(80)   NULL AFTER `protocol_filename`,
    ADD COLUMN `protocol_uploaded_at` TIMESTAMP     NULL DEFAULT NULL AFTER `protocol_mime`;
