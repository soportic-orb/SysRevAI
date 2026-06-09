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
    public function show(string $id): void
    {
        $article = $this->loadOrDeny((int) $id);
        $row    = ArticleCriticalReport::find((int) $article['id']);
        $report = $row !== null ? ArticleCriticalReport::decode($row) : null;
        $hasText = trim((string) ($article['extracted_text'] ?? '')) !== '';

        echo View::render('articles/critical_report', [
            'article'   => $article,
            'report'    => $report,
            'reportRow' => $row,
            'hasText'   => $hasText,
            'axes'      => ArticleCriticalReport::AXES,
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

        @set_time_limit(180);
        $result = ClaudeService::fromSettings()->articleCriticalReport($article, current_locale());

        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            $err = (string) ($result['error'] ?? 'failed');
            ActivityLog::record('articles.critical_failed', ['article_id' => $aid, 'error' => $err]);
            Session::flash('error', __('articles.critical.failed'));
            redirect($back);
        }

        $data = $this->sanitise($result['data']);
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
     * normalised into ['section', 'items'] tuples, both strings trimmed.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sanitise(array $data): array
    {
        $clamp = static function ($v): int {
            return max(0, min(100, (int) (is_numeric($v) ? $v : 0)));
        };
        $clean = [];
        foreach (ArticleCriticalReport::AXES as $axis) {
            $clean[$axis] = $clamp($data[$axis] ?? 0);
            $clean[$axis . '_note'] = trim((string) ($data[$axis . '_note'] ?? ''));
        }
        $clean['summary']         = trim((string) ($data['summary']         ?? ''));
        $clean['devils_advocate'] = trim((string) ($data['devils_advocate'] ?? ''));
        $clean['overall']         = trim((string) ($data['overall']         ?? ''));

        $recs = [];
        foreach ((array) ($data['recommendations'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $section = trim((string) ($r['section'] ?? ''));
            $items   = [];
            foreach ((array) ($r['items'] ?? []) as $it) {
                $s = trim((string) $it);
                if ($s !== '') {
                    $items[] = mb_substr($s, 0, 600);
                }
            }
            if ($section === '' && $items === []) {
                continue;
            }
            $recs[] = ['section' => mb_substr($section, 0, 200), 'items' => $items];
            if (count($recs) >= 12) {
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
