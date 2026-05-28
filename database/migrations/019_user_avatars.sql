-- ─────────────────────────────────────────────────────────────────────────────
-- 019_user_avatars.sql — Optional profile picture per user.
--
-- Stored as a relative path under public/uploads/avatars/ (e.g.
-- "avatars/42-abc.jpg"). NULL means "no avatar" → the UI falls back to a
-- coloured initials badge.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}users`
    ADD COLUMN `avatar_path` VARCHAR(255) NULL DEFAULT NULL AFTER `notification_preferences`;
