-- ─────────────────────────────────────────────────────────────────────────────
-- 038_copilot_actions.sql — Make Copilot agentic: persist proposed
-- write-actions so the user can approve them with one click.
--
-- The Copilot can now suggest concrete mutations (set the persistent
-- duplicates-removed counter, delete a reference, change a screening
-- decision, edit the screening guide, …). Whenever Claude proposes one,
-- the assistant turn is written to copilot_messages and the structured
-- proposal is stored on the SAME row in pending_action so the client
-- can never forge what gets executed — the confirm endpoint takes only
-- the message id and reads the proposal back from here.
--
-- pending_action_status walks the small state machine:
--   pending  → assistant proposed it; awaiting user approval
--   accepted → executor about to run
--   executed → executor finished, result stored in pending_action_result
--   rejected → user dismissed it
--   failed   → executor blew up (reason in pending_action_result.error)
--
-- pending_action_result captures the human-readable outcome to render
-- inline in the chat after the user acted on the proposal.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}copilot_messages`
    ADD COLUMN `pending_action`         JSON                                    NULL AFTER `content`,
    ADD COLUMN `pending_action_status`  ENUM('pending','accepted','executed',
                                              'rejected','failed')              NULL DEFAULT NULL AFTER `pending_action`,
    ADD COLUMN `pending_action_result`  JSON                                    NULL AFTER `pending_action_status`;
