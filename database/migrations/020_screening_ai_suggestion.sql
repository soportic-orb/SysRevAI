-- ─────────────────────────────────────────────────────────────────────────────
-- 020_screening_ai_suggestion.sql — Record the AI's recommendation alongside
-- each reviewer's decision so the trail shows whether the AI agreed or not.
--
-- Stored as JSON: { "recommendation": "include|exclude|maybe", "confidence":
-- 0.0-1.0, "reason": "...", "language": "es", "shown_at": "ISO timestamp" }.
-- NULL = the reviewer didn't ask the Copilot for a suggestion on this article.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}screening_decisions`
    ADD COLUMN `ai_suggestion` JSON NULL DEFAULT NULL AFTER `notes`;
