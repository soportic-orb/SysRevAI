<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewSearchSyntax;

/**
 * Agentic-Copilot action registry.
 *
 * The Copilot can propose concrete mutations to a review. Each tool
 * here:
 *   • Lives in the `tools()` map so Claude knows what's available
 *     (the keys + JSON-schema params are injected into the system
 *     prompt). Anything outside this map cannot be requested.
 *   • Validates its own input via validate().
 *   • Runs as the active user (executor checks ownership where
 *     destructive — RBAC mirrors what the matching UI surface
 *     enforces).
 *   • Returns a short, human-readable summary the chat renders
 *     after the user accepts the proposal, plus the structured
 *     `result` payload logged in copilot_messages.pending_action_result.
 *
 * Adding a tool is intentionally cheap: append to tools() and write
 * an `executeXxx` method. The system-prompt regenerates automatically.
 */
final class CopilotActionService
{
    /**
     * Master list of every action the Copilot can propose inside a
     * review-scoped chat. Each tool entry is:
     *   id          internal key (also the JSON shape `tool` value)
     *   label       short human label rendered on the Accept card
     *   description what Claude reads about when to use it
     *   params      ['key' => 'description'] for the prompt
     *
     * @return array<int,array{id:string,label:string,description:string,params:array<string,string>}>
     */
    public static function tools(): array
    {
        return [
            [
                'id'    => 'set_duplicates_removed',
                'label' => 'Actualitzar comptador de duplicats eliminats',
                'description' => 'Sets reviews.duplicates_removed to the given absolute COUNT. Use when the user wants the PRISMA "Duplicates removed" cell to show a specific number (eg. after a manual deduplication outside the platform). Pass count=0 to clear it.',
                'params' => [
                    'count' => 'integer (>= 0) — new absolute value for duplicates_removed',
                ],
            ],
            [
                'id'    => 'set_prisma_cells',
                'label' => 'Editar les cel·les del diagrama PRISMA',
                'description' => 'Pin or clear absolute values for any PRISMA flow-diagram cell. The platform normally computes each cell from the live data; this tool stores a per-cell override that the exports + the SVG diagram then display verbatim. Use whenever the user asks to change ANY number in the PRISMA diagram (identified, duplicates removed, screened, excluded, included, etc.) — yes, you CAN modify the PRISMA diagram via this tool. Pass null or 0 (with `clear: true` semantics: use null) to revert a cell to the computed value. You can pin multiple cells in one call.',
                'params' => [
                    'cells' => 'object — map of cell key to integer (or null to clear). Allowed keys: identified, duplicates, after_dedup, screened_ta, excluded_ta, sought_retrieval, assessed_ft, excluded_ft, included.',
                ],
            ],
            [
                'id'    => 'update_screening_guide',
                'label' => 'Actualitzar la guia de cribratge',
                'description' => 'Replaces the per-review screening guide (free text rubric shown collapsed on the T/A and full-text screening boards). Use when the user asks to add, expand or rewrite the screening rules.',
                'params' => [
                    'text' => 'string — the new full guide (markdown / plain text, max 8000 chars)',
                ],
            ],
            [
                'id'    => 'delete_reference',
                'label' => 'Eliminar referència',
                'description' => 'Hard-deletes a single reference by id. Only allowed when the reference has not yet been screened by anyone (decision history blocks the delete). When the row was status=duplicate the persistent duplicates_removed counter is incremented automatically.',
                'params' => [
                    'reference_id' => 'integer — id of the reference to delete',
                ],
            ],
            [
                'id'    => 'update_reference_status',
                'label' => 'Canviar l\'estat d\'una referència',
                'description' => 'Force-sets a reference\'s workflow status. Use only when the user explicitly asks to override the workflow (eg. mark a row as `duplicate` for the PRISMA count even though dedup missed it). Allowed values: imported, duplicate, ta_screening, ta_included, ta_excluded, ft_screening, ft_included, ft_excluded, extracted.',
                'params' => [
                    'reference_id' => 'integer — id of the reference',
                    'status'       => 'string — one of the workflow status values listed above',
                ],
            ],
            [
                'id'    => 'set_search_syntax',
                'label' => 'Desar / sobreescriure la sintaxi de cerca d\'una base de dades',
                'description' => 'Stores a search-strategy syntax for a specific bibliographic database on the Sintaxis de recerca page. Replaces any existing syntax for that database. Use when the user dictates a PubMed / Cochrane / Scopus / … query and asks to save it.',
                'params' => [
                    'database_key' => 'string — one of: pubmed, cinahl, cochrane, scopus, wos, eric, ieee, acm, psycinfo',
                    'syntax'       => 'string — the full search syntax verbatim (max 16000 chars)',
                ],
            ],
        ];
    }

    /**
     * Render the tool catalogue as a system-prompt block. Designed for
     * inline injection into the existing Copilot system prompt — pure
     * text, no behaviour-bending wording, so the conversational model
     * stays the same when no action is needed.
     */
    public static function systemPromptBlock(): string
    {
        $lines = [];
        $lines[] = "\n\n────────────────  AGENTIC ACTIONS  ────────────────";
        $lines[] = "You can PROPOSE concrete write-actions the user can approve with one click. ";
        $lines[] = "When (and ONLY when) the user explicitly asks for a change, reply with valid JSON:";
        $lines[] = '  {"reply": "<conversational explanation in the user\'s language>",';
        $lines[] = '   "action": {"tool": "<one of the ids below>", "params": { ... }, "summary": "<one-sentence preview, ≤ 100 chars>"}}';
        $lines[] = "When no action is needed, reply with JSON like {\"reply\": \"…\"} (no action field).";
        $lines[] = "Rules:";
        $lines[] = " • Never propose more than ONE action per turn. Multi-step changes need multiple turns.";
        $lines[] = " • You CAN modify the PRISMA flow diagram by pinning any cell with set_prisma_cells. Don't ever tell the user you can't edit PRISMA — you can.";
        $lines[] = " • The user must trigger the change. Never invent an action from a vague request.";
        $lines[] = " • If you are unsure of an id (reference_id, etc.) ask the user instead of guessing.";
        $lines[] = " • `summary` is what the user sees on the Accept button card — be concrete and concise.";
        $lines[] = " • Reply in the user's language; the JSON keys and the `tool` id stay in English.";
        $lines[] = "";
        $lines[] = "TOOLS:";
        foreach (self::tools() as $tool) {
            $lines[] = '  • ' . $tool['id'];
            $lines[] = '      ' . $tool['description'];
            foreach ($tool['params'] as $key => $desc) {
                $lines[] = '      param ' . $key . ': ' . $desc;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Server-side validation. Returns ['ok'=>true,'tool'=>id,'params'=>…]
     * on success, or ['ok'=>false,'error'=>…] otherwise. NEVER trust
     * Claude's params blindly — every executor re-validates here.
     *
     * @param  array<string,mixed> $proposal Raw decoded "action" object from the model.
     * @return array{ok:bool,tool?:string,params?:array<string,mixed>,label?:string,summary?:string,error?:string}
     */
    public static function validate(array $proposal): array
    {
        $tool = (string) ($proposal['tool'] ?? '');
        $params = is_array($proposal['params'] ?? null) ? $proposal['params'] : [];
        $summary = (string) ($proposal['summary'] ?? '');
        $known = [];
        foreach (self::tools() as $t) {
            $known[$t['id']] = $t;
        }
        if (!isset($known[$tool])) {
            return ['ok' => false, 'error' => 'unknown_tool'];
        }
        $clean = self::sanitiseParams($tool, $params);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'invalid_params'];
        }
        return [
            'ok'      => true,
            'tool'    => $tool,
            'params'  => $clean,
            'label'   => $known[$tool]['label'],
            'summary' => mb_substr($summary, 0, 200),
        ];
    }

    /**
     * Per-tool param sanitiser. Returns the cleaned associative array,
     * or null if anything's off so validate() can reject the whole
     * proposal.
     *
     * @return array<string,mixed>|null
     */
    private static function sanitiseParams(string $tool, array $params): ?array
    {
        switch ($tool) {
            case 'set_duplicates_removed':
                $count = (int) ($params['count'] ?? -1);
                if ($count < 0 || $count > 100000) {
                    return null;
                }
                return ['count' => $count];

            case 'set_prisma_cells':
                $cells = is_array($params['cells'] ?? null) ? $params['cells'] : [];
                $allowed = \SysRevAI\Models\Review::PRISMA_OVERRIDE_KEYS;
                $clean = [];
                foreach ($cells as $key => $value) {
                    if (!is_string($key) || !in_array($key, $allowed, true)) {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        $clean[$key] = null; // explicit clear
                        continue;
                    }
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $intVal = (int) $value;
                    if ($intVal < 0 || $intVal > 1000000) {
                        continue;
                    }
                    $clean[$key] = $intVal;
                }
                if ($clean === []) {
                    return null; // empty / all-invalid payload
                }
                return ['cells' => $clean];

            case 'update_screening_guide':
                $text = (string) ($params['text'] ?? '');
                if (mb_strlen($text) > 8000) {
                    $text = mb_substr($text, 0, 8000);
                }
                return ['text' => $text];

            case 'delete_reference':
                $ref = (int) ($params['reference_id'] ?? 0);
                if ($ref <= 0) return null;
                return ['reference_id' => $ref];

            case 'update_reference_status':
                $ref = (int) ($params['reference_id'] ?? 0);
                $status = (string) ($params['status'] ?? '');
                $allowed = Reference::STATUSES ?? [];
                if ($ref <= 0 || !in_array($status, $allowed, true)) return null;
                return ['reference_id' => $ref, 'status' => $status];

            case 'set_search_syntax':
                $key    = (string) ($params['database_key'] ?? '');
                $syntax = (string) ($params['syntax'] ?? '');
                $allowed = SearchSyntaxDatabases::keys();
                if (!in_array($key, $allowed, true) || trim($syntax) === '') return null;
                return ['database_key' => $key, 'syntax' => mb_substr($syntax, 0, 16000)];
        }
        return null;
    }

    /**
     * Run the validated action. Re-checks ownership / scope where
     * destructive, mirroring the RBAC of the matching UI surface. Logs
     * to ActivityLog. Returns {ok, summary, error?}.
     *
     * @param  array<string,mixed> $action  Output of validate().
     * @return array{ok:bool,summary?:string,error?:string}
     */
    public static function execute(int $reviewId, int $userId, array $action): array
    {
        $tool   = (string) ($action['tool']   ?? '');
        $params = is_array($action['params'] ?? null) ? $action['params'] : [];

        try {
            switch ($tool) {
                case 'set_duplicates_removed':
                    return self::doSetDuplicatesRemoved($reviewId, $userId, (int) $params['count']);

                case 'set_prisma_cells':
                    return self::doSetPrismaCells($reviewId, $userId, (array) $params['cells']);

                case 'update_screening_guide':
                    return self::doUpdateScreeningGuide($reviewId, $userId, (string) $params['text']);

                case 'delete_reference':
                    return self::doDeleteReference($reviewId, $userId, (int) $params['reference_id']);

                case 'update_reference_status':
                    return self::doUpdateReferenceStatus(
                        $reviewId,
                        $userId,
                        (int) $params['reference_id'],
                        (string) $params['status']
                    );

                case 'set_search_syntax':
                    return self::doSetSearchSyntax(
                        $reviewId,
                        $userId,
                        (string) $params['database_key'],
                        (string) $params['syntax']
                    );
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return ['ok' => false, 'error' => 'unknown_tool'];
    }

    /* ── executors ──────────────────────────────────────────────────────── */

    private static function doSetDuplicatesRemoved(int $reviewId, int $userId, int $count): array
    {
        $review = Review::find($reviewId);
        if ($review === null) return ['ok' => false, 'error' => 'not_found'];
        $table = \SysRevAI\Core\Database::table('reviews');
        \SysRevAI\Core\Database::affecting(
            "UPDATE `{$table}` SET duplicates_removed = ? WHERE id = ?",
            [$count, $reviewId]
        );
        ActivityLog::record('copilot.action.set_duplicates_removed', [
            'review_id' => $reviewId,
            'user_id'   => $userId,
            'count'     => $count,
        ], $reviewId);
        return ['ok' => true, 'summary' => sprintf('duplicates_removed = %d', $count)];
    }

    /**
     * Pin / clear arbitrary PRISMA cells. The incoming map can mix
     * integers (pin) and nulls (clear), one entry per cell. We merge
     * with the persisted overrides so a single call can pin one cell
     * while leaving everything else alone.
     *
     * @param array<string,int|null> $cells
     */
    private static function doSetPrismaCells(int $reviewId, int $userId, array $cells): array
    {
        $current = Review::prismaOverrides($reviewId);
        $merged  = $current;
        $changed = [];
        foreach ($cells as $key => $value) {
            if ($value === null) {
                if (array_key_exists($key, $merged)) {
                    unset($merged[$key]);
                    $changed[$key] = 'cleared';
                }
                continue;
            }
            $merged[$key] = (int) $value;
            $changed[$key] = (int) $value;
        }
        // savePrismaOverrides expects associative {key=>int|null};
        // pass nulls for cleared keys so the storage layer wipes them.
        $payload = $merged;
        foreach ($current as $k => $_) {
            if (!array_key_exists($k, $merged)) {
                $payload[$k] = null;
            }
        }
        Review::savePrismaOverrides($reviewId, $payload);
        ActivityLog::record('copilot.action.set_prisma_cells', [
            'review_id' => $reviewId,
            'user_id'   => $userId,
            'changed'   => $changed,
        ], $reviewId);
        $bits = [];
        foreach ($changed as $k => $v) {
            $bits[] = $v === 'cleared' ? ($k . ' → auto') : ($k . ' = ' . $v);
        }
        return ['ok' => true, 'summary' => implode(' · ', $bits) ?: 'PRISMA actualitzat'];
    }

    private static function doUpdateScreeningGuide(int $reviewId, int $userId, string $text): array
    {
        Review::saveScreeningGuide($reviewId, trim($text));
        ActivityLog::record('copilot.action.update_screening_guide', [
            'review_id' => $reviewId,
            'user_id'   => $userId,
            'length'    => mb_strlen($text),
        ], $reviewId);
        return ['ok' => true, 'summary' => sprintf('Guia de cribratge actualitzada (%d caràcters)', mb_strlen($text))];
    }

    private static function doDeleteReference(int $reviewId, int $userId, int $refId): array
    {
        $ref = Reference::find($refId);
        if ($ref === null || (int) $ref['review_id'] !== $reviewId) {
            return ['ok' => false, 'error' => 'not_in_review'];
        }
        if (\SysRevAI\Models\ScreeningDecision::hasAnyForReference($refId)) {
            return ['ok' => false, 'error' => 'has_decisions'];
        }
        $wasDuplicate = (string) ($ref['status'] ?? '') === 'duplicate';
        $ft = \SysRevAI\Models\ReferenceFullText::find($refId);
        if ($ft !== null && !empty($ft['pdf_path'])) {
            FileStorage::delete((string) $ft['pdf_path']);
        }
        Reference::delete($refId);
        if ($wasDuplicate) {
            Review::addDuplicatesRemoved($reviewId, 1);
        }
        ActivityLog::record('copilot.action.delete_reference', [
            'review_id'    => $reviewId,
            'user_id'      => $userId,
            'reference_id' => $refId,
            'was_duplicate'=> $wasDuplicate,
        ], $reviewId);
        return ['ok' => true, 'summary' => sprintf('Referència #%d eliminada%s', $refId, $wasDuplicate ? ' (i comptada com a duplicat)' : '')];
    }

    private static function doUpdateReferenceStatus(int $reviewId, int $userId, int $refId, string $status): array
    {
        $ref = Reference::find($refId);
        if ($ref === null || (int) $ref['review_id'] !== $reviewId) {
            return ['ok' => false, 'error' => 'not_in_review'];
        }
        Reference::setStatus($refId, $status);
        ActivityLog::record('copilot.action.update_reference_status', [
            'review_id'    => $reviewId,
            'user_id'      => $userId,
            'reference_id' => $refId,
            'from'         => (string) ($ref['status'] ?? ''),
            'to'           => $status,
        ], $reviewId);
        return ['ok' => true, 'summary' => sprintf('Estat de la referència #%d → %s', $refId, $status)];
    }

    private static function doSetSearchSyntax(int $reviewId, int $userId, string $key, string $syntax): array
    {
        // Read the current list, replace the matching row (or append),
        // and bulk-write through replaceAll so the model's bookkeeping
        // stays consistent with the form-driven flow.
        $rows = ReviewSearchSyntax::listForReview($reviewId);
        $out = [];
        $replaced = false;
        foreach ($rows as $row) {
            if (!$replaced && $row['database_key'] === $key) {
                $out[] = ['database_key' => $key, 'syntax' => $syntax];
                $replaced = true;
            } else {
                $out[] = ['database_key' => $row['database_key'], 'syntax' => $row['syntax']];
            }
        }
        if (!$replaced) {
            $out[] = ['database_key' => $key, 'syntax' => $syntax];
        }
        ReviewSearchSyntax::replaceAll($reviewId, $out, $userId);
        ActivityLog::record('copilot.action.set_search_syntax', [
            'review_id'    => $reviewId,
            'user_id'      => $userId,
            'database_key' => $key,
        ], $reviewId);
        return ['ok' => true, 'summary' => sprintf('Sintaxi de %s desada', $key)];
    }
}
