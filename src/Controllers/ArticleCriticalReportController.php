<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Services\ClaudeService;

/**
 * AI-generated critical report per article. Renders the cached report
 * on GET; POST re-runs Claude with the article's extracted text and
 * overwrites the previous evaluation.
 */
final class ArticleCriticalReportController
{
    /** Languages the user can have the report written in. 'ca' is the default. */
    public const REPORT_LANGUAGES = ['ca', 'es', 'en', 'fr'];

    public function show(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $row    = ArticleCriticalReport::find((int) $article['id']);
        $report = $row !== null ? ArticleCriticalReport::decode($row) : null;
        $hasText = trim((string) ($article['extracted_text'] ?? '')) !== '';

        echo View::render('articles/critical_report', [
            'article'   => $article,
            'isOwner'   => Article::isOwner($article, (int) Auth::id()),
            'report'    => $report,
            'reportRow' => $row,
            'hasText'   => $hasText,
            'axes'      => ArticleCriticalReport::AXES,
            'languages' => self::REPORT_LANGUAGES,
        ]);
    }

    public function generate(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $aid = (int) $article['id'];
        $back = '/tools/articles/' . $aid . '/critical-report';

        if (trim((string) ($article['extracted_text'] ?? '')) === '') {
            Session::flash('error', __('articles.critical.no_text'));
            redirect($back);
        }

        // Report language, chosen by the user in the generate/re-run form.
        // Whitelisted so a forged POST can't inject an arbitrary string
        // into the prompt; Catalan is the product default.
        $language = (string) ($_POST['report_language'] ?? '');
        if (!in_array($language, self::REPORT_LANGUAGES, true)) {
            $language = 'ca';
        }

        // Opus emitting a deep multi-section JSON can take several minutes
        // end-to-end; the inner cURL call is allowed up to 300 s and we
        // want a small margin on top for sanitisation, persistence and the
        // redirect so PHP never kills the worker mid-request.
        @set_time_limit(360);
        @ini_set('max_execution_time', '360');
        $result = ClaudeService::fromSettings()->articleCriticalReport($article, $language);

        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            $err = (string) ($result['error'] ?? 'failed');
            ActivityLog::record('articles.critical_failed', ['article_id' => $aid, 'error' => $err]);
            Session::flash('error', __('articles.critical.failed'));
            redirect($back);
        }

        $data = $this->sanitise($result['data']);
        // Persisted alongside the content so the web view and the Word/PDF
        // exports can render section headings in the report's own language
        // rather than whatever the UI locale happens to be at export time.
        $data['language'] = $language;
        ArticleCriticalReport::save(
            $aid,
            $data,
            (string) (setting('claude.model_complex') ?? 'claude-opus-4-7'),
            (int) Auth::id()
        );
        ActivityLog::record('articles.critical_generated', ['article_id' => $aid]);
        Session::flash('success', __('articles.critical.generated'));
        redirect($back);
    }

    /**
     * Whitelist + clamp the model's JSON so the view never has to defend
     * against an out-of-range score or a missing key. Recommendations are
     * normalised into ['section', 'items'] tuples; each item is an
     * ['text', 'priority'] pair (priority is one of high / medium / low).
     * Plain-string items from older payloads are accepted and default to
     * 'medium' priority so existing reports keep rendering.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sanitise(array $data): array
    {
        $clamp = static function ($v): int {
            return max(0, min(100, (int) (is_numeric($v) ? $v : 0)));
        };
        $bullets = static function (mixed $raw, int $maxItems, int $maxChars): array {
            $out = [];
            foreach ((array) $raw as $entry) {
                $s = trim((string) (is_array($entry) ? ($entry['text'] ?? '') : $entry));
                if ($s !== '') {
                    $out[] = mb_substr($s, 0, $maxChars);
                    if (count($out) >= $maxItems) {
                        break;
                    }
                }
            }
            return $out;
        };

        $clean = [];
        foreach (ArticleCriticalReport::AXES as $axis) {
            $clean[$axis] = $clamp($data[$axis] ?? 0);
            $clean[$axis . '_note'] = trim((string) ($data[$axis . '_note'] ?? ''));
        }
        $clean['summary']               = trim((string) ($data['summary']               ?? ''));
        $clean['overall']               = trim((string) ($data['overall']               ?? ''));
        $clean['devils_advocate']       = trim((string) ($data['devils_advocate']       ?? ''));
        $clean['methodology_critique']  = trim((string) ($data['methodology_critique']  ?? ''));
        $clean['literature_positioning'] = trim((string) ($data['literature_positioning'] ?? ''));
        $clean['publication_outlook']   = trim((string) ($data['publication_outlook']   ?? ''));

        $clean['key_strengths']        = $bullets($data['key_strengths']        ?? [], 10, 600);
        $clean['key_weaknesses']       = $bullets($data['key_weaknesses']       ?? [], 10, 600);
        $clean['statistical_concerns'] = $bullets($data['statistical_concerns'] ?? [], 10, 600);
        $clean['ethical_concerns']     = $bullets($data['ethical_concerns']     ?? [], 10, 600);
        $clean['reproducibility_notes'] = $bullets($data['reproducibility_notes'] ?? [], 10, 600);

        $validPriority = ['high', 'medium', 'low'];
        $recs = [];
        foreach ((array) ($data['recommendations'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $section = trim((string) ($r['section'] ?? ''));
            $items   = [];
            foreach ((array) ($r['items'] ?? []) as $it) {
                if (is_array($it)) {
                    $text = trim((string) ($it['text'] ?? ''));
                    $priority = strtolower(trim((string) ($it['priority'] ?? 'medium')));
                } else {
                    $text = trim((string) $it);
                    $priority = 'medium';
                }
                if (!in_array($priority, $validPriority, true)) {
                    $priority = 'medium';
                }
                if ($text !== '') {
                    $items[] = ['text' => mb_substr($text, 0, 600), 'priority' => $priority];
                }
            }
            if ($section === '' && $items === []) {
                continue;
            }
            $recs[] = ['section' => mb_substr($section, 0, 200), 'items' => $items];
            if (count($recs) >= 14) {
                break;
            }
        }
        $clean['recommendations'] = $recs;
        return $clean;
    }

    private function loadOrDeny(int $id): array
    {
        $article = Article::find($id);
        $uid = (int) Auth::id();
        if ($article === null || !Article::userCanAccess($id, $uid)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $article;
    }
}
