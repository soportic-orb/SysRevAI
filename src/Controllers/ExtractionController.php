<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\ExtractionData;
use SysRevAI\Models\ExtractionTemplate;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\ExtractionService;

/**
 * Data extraction: per-review template, per-(reference, reviewer) submissions,
 * AI-assisted fill, owner approval, and a side-by-side compare view.
 */
final class ExtractionController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        $template = ExtractionTemplate::ensureDefault($rid);

        $list = Database::select(
            "SELECT id, title, authors_json, year FROM `" . Database::table('references') . "`
             WHERE review_id = ? AND status IN ('ft_included','extracted')
             ORDER BY id DESC",
            [$rid]
        );

        echo View::render('extraction/index', [
            'review'    => $review,
            'template'  => $template,
            'rows'      => $list,
            'statusMap' => ExtractionData::statusMap($rid),
        ]);
    }

    public function templateEdit(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $template = ExtractionTemplate::ensureDefault((int) $id);
        echo View::render('extraction/template', [
            'review'   => $review,
            'template' => $template,
            'fields'   => ExtractionTemplate::decodeFields($template),
        ]);
    }

    public function templateSave(string $id): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $template = ExtractionTemplate::ensureDefault((int) $id);

        $name = trim((string) ($_POST['name'] ?? 'Default extraction')) ?: 'Default extraction';
        $fields = $this->readTemplateFields($_POST);
        if ($fields === []) {
            Session::flash('error', __('extraction.template_empty'));
            redirect('/reviews/' . (int) $id . '/extraction/template');
        }

        ExtractionTemplate::update((int) $template['id'], $name, $fields);
        ActivityLog::record('extraction.template_updated', [], (int) $id);
        Session::flash('success', __('admin.saved'));
        redirect('/reviews/' . (int) $id . '/extraction/template');
    }

    public function edit(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $template = ExtractionTemplate::ensureDefault((int) $id);
        $fields = ExtractionTemplate::decodeFields($template);

        $own = ExtractionData::find((int) $reference['id'], (int) Auth::id(), (int) $template['id']);
        $others = array_values(array_filter(
            ExtractionData::forReference((int) $reference['id']),
            static fn ($row) => (int) $row['reviewer_id'] !== (int) Auth::id()
        ));

        echo View::render('extraction/edit', [
            'review'    => $review,
            'reference' => $reference,
            'template'  => $template,
            'fields'    => $fields,
            'own'       => $own,
            'data'      => $own !== null ? ExtractionData::decodeData($own) : [],
            'others'    => $others,
            'isOwner'   => (int) $review['owner_id'] === (int) Auth::id(),
        ]);
    }

    public function save(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $template = ExtractionTemplate::ensureDefault((int) $id);
        $fields = ExtractionTemplate::decodeFields($template);

        $clean = ExtractionService::sanitize($fields, $_POST);
        $status = (($_POST['action'] ?? 'draft') === 'submit') ? 'submitted' : 'draft';

        ExtractionData::upsert((int) $reference['id'], (int) Auth::id(), (int) $template['id'], $clean, $status);
        ActivityLog::record('extraction.saved', ['reference_id' => (int) $reference['id'], 'status' => $status], (int) $id);
        Session::flash('success', $status === 'submitted' ? __('extraction.submitted') : __('extraction.saved'));
        redirect('/reviews/' . (int) $id . '/extraction/' . (int) $reference['id']);
    }

    /** AI-assisted extraction: fill the reviewer's draft from the article text. */
    public function ai(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadRefOrDeny((int) $id, (int) $refId);
        $template = ExtractionTemplate::ensureDefault((int) $id);
        $fields = ExtractionTemplate::decodeFields($template);

        $ft = ReferenceFullText::find((int) $reference['id']);
        $text = (string) ($ft['extracted_text'] ?? ($reference['abstract'] ?? ''));
        if (trim($text) === '') {
            Session::flash('error', __('extraction.ai_no_text'));
            redirect('/reviews/' . (int) $id . '/extraction/' . (int) $reference['id']);
        }

        $shape = ExtractionService::templateForAi($fields);
        $result = ClaudeService::fromSettings()->extractStructuredData($text, $shape, (int) $id);

        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            Session::flash('error', __('extraction.ai_failed'));
            redirect('/reviews/' . (int) $id . '/extraction/' . (int) $reference['id']);
        }

        $clean = ExtractionService::sanitize($fields, (array) $result['data']);
        ExtractionData::upsert((int) $reference['id'], (int) Auth::id(), (int) $template['id'], $clean, 'draft');
        ActivityLog::record('extraction.ai_filled', ['reference_id' => (int) $reference['id']], (int) $id);
        Session::flash('success', __('extraction.ai_ok'));
        redirect('/reviews/' . (int) $id . '/extraction/' . (int) $reference['id']);
    }

    public function approve(string $id, string $refId): void
    {
        $review = $this->ownerOrDeny((int) $id);
        $extractionId = (int) ($_POST['extraction_id'] ?? 0);
        $referenceId = (int) $refId;

        $template = ExtractionTemplate::ensureDefault((int) $id);
        $found = false;
        foreach (ExtractionData::forReference($referenceId) as $row) {
            if ((int) $row['id'] === $extractionId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            redirect('/reviews/' . (int) $id . '/extraction/' . $referenceId);
        }

        ExtractionData::approve($extractionId, (int) Auth::id());
        Reference::setStatus($referenceId, 'extracted');
        ActivityLog::record('extraction.approved', ['extraction_id' => $extractionId, 'reference_id' => $referenceId], (int) $id);
        Session::flash('success', __('extraction.approved'));
        redirect('/reviews/' . (int) $id . '/extraction/' . $referenceId);
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /**
     * Decode the field rows posted from the template editor.
     * Form posts: fields[i][key], fields[i][label], fields[i][type], fields[i][options]
     */
    private function readTemplateFields(array $posted): array
    {
        $rows = $posted['fields'] ?? [];
        if (!is_array($rows)) {
            return [];
        }
        $clean = [];
        foreach ($rows as $row) {
            $key = preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($row['key'] ?? ''))) ?? '';
            $key = trim($key, '_');
            if ($key === '') {
                continue;
            }
            $type = (string) ($row['type'] ?? 'text');
            if (!in_array($type, ExtractionTemplate::FIELD_TYPES, true)) {
                $type = 'text';
            }
            $field = [
                'key'   => $key,
                'label' => trim((string) ($row['label'] ?? $key)) ?: $key,
                'type'  => $type,
            ];
            if (in_array($type, ['select', 'multi_select'], true)) {
                $opts = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', (string) ($row['options'] ?? '')) ?: []
                )));
                $field['options'] = $opts;
            }
            $clean[] = $field;
        }
        return $clean;
    }

    /** @return array{0:array,1:array} [review, reference] */
    private function loadRefOrDeny(int $reviewId, int $refId): array
    {
        $review = Review::find($reviewId);
        $reference = Reference::find($refId);
        if ($review === null || $reference === null
            || (int) $reference['review_id'] !== $reviewId
            || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return [$review, $reference];
    }

    private function memberOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }

    private function ownerOrDeny(int $reviewId): array
    {
        $review = $this->memberOrDeny($reviewId);
        if ((int) $review['owner_id'] !== (int) Auth::id()) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
