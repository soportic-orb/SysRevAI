-- ─────────────────────────────────────────────────────────────────────────────
-- 022_users_legal_accepted_at.sql — Audit field for legal acceptance.
--
-- Existing users keep evidence of consent (their original signup predates
-- this column) by backfilling with `created_at`. New signups and invited
-- accounts set this column explicitly when the acceptance checkbox is ticked.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}users`
    ADD COLUMN `legal_accepted_at` TIMESTAMP NULL DEFAULT NULL AFTER `email_verified_at`;

UPDATE `{prefix}users`
   SET `legal_accepted_at` = `created_at`
 WHERE `legal_accepted_at` IS NULL;
