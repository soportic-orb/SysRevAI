<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\AiSummary;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;

/**
 * AI-generated structured summaries (background/methods/results/conclusions/
 * relevance), cached per (reference, language). Translation is handled by the
 * generic /reviews/{id}/translate JSON endpoint.
 */
final class SummariesController
{
    private const ALLOWED_LANGS = ['ca', 'es', 'en'];

    public function show(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $lang = $this->resolveLang((string) ($_GET['lang'] ?? ''));

        $summaryRow = AiSummary::find((int) $reference['id'], $lang);
        $summary = $summaryRow !== null ? AiSummary::decode($summaryRow) : null;

        echo View::render('summaries/show', [
            'review'      => $review,
            'reference'   => $reference,
            'lang'        => $lang,
            'langs'       => self::ALLOWED_LANGS,
            'summary'     => $summary,
            'summaryRow'  => $summaryRow,
            'sections'    => AiSummary::SECTIONS,
        ]);
    }

    public function generate(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $lang = $this->resolveLang((string) ($_POST['lang'] ?? ''));

        $ft = ReferenceFullText::find((int) $reference['id']);
        $text = (string) ($ft['extracted_text'] ?? ($reference['abstract'] ?? ''));
        if (trim($text) === '') {
            Session::flash('error', __('summary.no_text'));
            redirect('/reviews/' . (int) $id . '/references/' . (int) $reference['id'] . '/summary?lang=' . $lang);
        }

        $result = ClaudeService::fromSettings()->summarize($text, $lang, (int) $id);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            Session::flash('error', __('summary.failed'));
            redirect('/reviews/' . (int) $id . '/references/' . (int) $reference['id'] . '/summary?lang=' . $lang);
        }

        $clean = [];
        foreach (AiSummary::SECTIONS as $section) {
            $value = $result['data'][$section] ?? '';
            $clean[$section] = is_string($value) ? trim($value) : '';
        }

        AiSummary::save(
            (int) $reference['id'],
            $lang,
            $clean,
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7')
        );
        ActivityLog::record('summary.generated', ['reference_id' => (int) $reference['id'], 'lang' => $lang], (int) $id);
        Session::flash('success', __('summary.generated'));
        redirect('/reviews/' . (int) $id . '/references/' . (int) $reference['id'] . '/summary?lang=' . $lang);
    }

    private function resolveLang(string $candidate): string
    {
        if (in_array($candidate, self::ALLOWED_LANGS, true)) {
            return $candidate;
        }
        $user = Auth::user();
        $userLocale = (string) ($user['locale'] ?? '');
        if (in_array($userLocale, self::ALLOWED_LANGS, true)) {
            return $userLocale;
        }
        return (string) (setting('app.locale') ?? 'ca');
    }

    /** @return array{0:array,1:array} */
    private function loadOrDeny(int $reviewId, int $refId): array
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
}
