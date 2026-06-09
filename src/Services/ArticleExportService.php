<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Models\CopilotMessage;

/**
 * Renders an article's critical report (and, optionally, the full
 * Copilot chat transcript) as DOCX via PhpWord or PDF via Dompdf.
 *
 * Two scopes are supported, controlled by the user from the workspace:
 *   - 'report'      → just the critical report sections.
 *   - 'report_chat' → critical report + chat transcript appended.
 *
 * Both writers consume the same internal struct produced by
 * {@see self::collect()} so the two output formats stay in sync.
 */
final class ArticleExportService
{
    public const SCOPE_REPORT      = 'report';
    public const SCOPE_REPORT_CHAT = 'report_chat';

    /** @return list<string> */
    public static function scopes(): array
    {
        return [self::SCOPE_REPORT, self::SCOPE_REPORT_CHAT];
    }

    /**
     * Build the language-agnostic export payload: the report axes / prose
     * blocks, optional chat transcript and the article header. Callers
     * pass the article row + the resolved current user id; the service
     * pulls the cached report and the chat history itself.
     *
     * @param array<string,mixed> $article
     * @return array{
     *   article: array<string,mixed>,
     *   report: ?array<string,mixed>,
     *   reportRow: ?array<string,mixed>,
     *   chat: list<array{role:string,content:string,created_at:string}>,
     *   include_chat: bool
     * }
     */
    private static function collect(array $article, int $userId, string $scope): array
    {
        $aid = (int) ($article['id'] ?? 0);
        $row = ArticleCriticalReport::find($aid);
        $report = $row !== null ? ArticleCriticalReport::decode($row) : null;

        $includeChat = $scope === self::SCOPE_REPORT_CHAT;
        $chat = [];
        if ($includeChat) {
            foreach (CopilotMessage::history(null, $userId, 500, $aid) as $m) {
                $chat[] = [
                    'role'       => (string) ($m['role'] ?? ''),
                    'content'    => (string) ($m['content'] ?? ''),
                    'created_at' => (string) ($m['created_at'] ?? ''),
                ];
            }
        }

        return [
            'article'      => $article,
            'report'       => $report,
            'reportRow'    => $row,
            'chat'         => $chat,
            'include_chat' => $includeChat,
        ];
    }

    /**
     * @param array<string,mixed> $article
     * @return array{bytes:?string,error:?string}
     */
    public static function docx(array $article, int $userId, string $scope): array
    {
        if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            return ['bytes' => null, 'error' => 'phpword_not_installed'];
        }
        $data = self::collect($article, $userId, $scope);
        if ($data['report'] === null) {
            return ['bytes' => null, 'error' => 'no_report'];
        }

        $doc = new \PhpOffice\PhpWord\PhpWord();
        $doc->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('es-ES'));
        $section = $doc->addSection();

        $title = (string) ($article['title'] ?: '—');
        $section->addTitle($title, 1);
        $section->addText(__('articles.export.doc_subtitle'));

        $report = $data['report'];
        if (!empty($report['overall'])) {
            $section->addTextBreak(1);
            $section->addText((string) $report['overall'], ['bold' => true]);
        }

        // Axes.
        $section->addTextBreak(1);
        $section->addTitle(__('articles.export.h_scores'), 2);
        foreach (ArticleCriticalReport::AXES as $axis) {
            $score = (int) ($report[$axis] ?? 0);
            $note  = (string) ($report[$axis . '_note'] ?? '');
            $label = (string) __('articles.critical.axis_' . $axis);
            $section->addText($label . ': ' . $score . '/100', ['bold' => true]);
            if ($note !== '') {
                $section->addText($note);
            }
        }

        if (!empty($report['summary'])) {
            $section->addTextBreak(1);
            $section->addTitle(__('articles.critical.h_summary'), 2);
            self::addParagraphs($section, (string) $report['summary']);
        }
        if (!empty($report['devils_advocate'])) {
            $section->addTextBreak(1);
            $section->addTitle(__('articles.critical.h_devils_advocate'), 2);
            self::addParagraphs($section, (string) $report['devils_advocate']);
        }

        $recs = (array) ($report['recommendations'] ?? []);
        if ($recs !== []) {
            $section->addTextBreak(1);
            $section->addTitle(__('articles.critical.h_recommendations'), 2);
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $section->addTitle($sec, 3);
                }
                foreach ((array) ($rec['items'] ?? []) as $it) {
                    $s = trim((string) $it);
                    if ($s !== '') {
                        $section->addListItem($s);
                    }
                }
            }
        }

        if ($data['include_chat'] && $data['chat'] !== []) {
            $section->addPageBreak();
            $section->addTitle(__('articles.export.h_chat'), 2);
            $section->addText(__('articles.export.chat_subtitle'));
            foreach ($data['chat'] as $m) {
                $section->addTextBreak(1);
                $who = $m['role'] === 'assistant'
                    ? (string) __('articles.export.who_assistant')
                    : (string) __('articles.export.who_user');
                $stamp = $m['created_at'] !== '' ? ' · ' . $m['created_at'] : '';
                $section->addText($who . $stamp, ['bold' => true]);
                self::addParagraphs($section, $m['content']);
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
        ob_start();
        $writer->save('php://output');
        $bytes = (string) ob_get_clean();
        return ['bytes' => $bytes, 'error' => null];
    }

    /**
     * @param array<string,mixed> $article
     * @return array{bytes:?string,error:?string}
     */
    public static function pdf(array $article, int $userId, string $scope): array
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return ['bytes' => null, 'error' => 'dompdf_not_installed'];
        }
        $data = self::collect($article, $userId, $scope);
        if ($data['report'] === null) {
            return ['bytes' => null, 'error' => 'no_report'];
        }

        $html = self::renderHtml($data);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return ['bytes' => $dompdf->output(), 'error' => null];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function renderHtml(array $data): string
    {
        $report = (array) ($data['report'] ?? []);
        $article = (array) ($data['article'] ?? []);
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $br  = static fn (string $s): string => nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $title = (string) ($article['title'] ?: '—');
        $axesHtml = '';
        foreach (ArticleCriticalReport::AXES as $axis) {
            $score = (int) ($report[$axis] ?? 0);
            $note  = (string) ($report[$axis . '_note'] ?? '');
            $label = (string) __('articles.critical.axis_' . $axis);
            $tone  = $score >= 80 ? '#0a7d4d' : ($score >= 50 ? '#a16207' : '#b91c1c');
            $axesHtml .= '<div class="axis"><div class="axis-head"><span class="axis-label">'
                . $esc($label)
                . '</span><span class="axis-score" style="color:' . $tone . '">'
                . $score . '/100</span></div>'
                . '<div class="axis-bar"><span class="axis-bar-fill" style="width:'
                . $score . '%; background:' . $tone . '"></span></div>';
            if ($note !== '') {
                $axesHtml .= '<p class="axis-note">' . $esc($note) . '</p>';
            }
            $axesHtml .= '</div>';
        }

        $blocks = '';
        if (!empty($report['summary'])) {
            $blocks .= '<h2>' . $esc((string) __('articles.critical.h_summary')) . '</h2>'
                . '<p>' . $br((string) $report['summary']) . '</p>';
        }
        if (!empty($report['devils_advocate'])) {
            $blocks .= '<h2 class="devil">' . $esc((string) __('articles.critical.h_devils_advocate')) . '</h2>'
                . '<p class="devil">' . $br((string) $report['devils_advocate']) . '</p>';
        }
        $recs = (array) ($report['recommendations'] ?? []);
        if ($recs !== []) {
            $blocks .= '<h2>' . $esc((string) __('articles.critical.h_recommendations')) . '</h2>';
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $blocks .= '<h3>' . $esc($sec) . '</h3>';
                }
                $items = (array) ($rec['items'] ?? []);
                if ($items !== []) {
                    $blocks .= '<ul>';
                    foreach ($items as $it) {
                        $s = trim((string) $it);
                        if ($s !== '') {
                            $blocks .= '<li>' . $esc($s) . '</li>';
                        }
                    }
                    $blocks .= '</ul>';
                }
            }
        }

        $chatHtml = '';
        if ($data['include_chat'] && $data['chat'] !== []) {
            $chatHtml .= '<div class="page-break"></div>'
                . '<h2>' . $esc((string) __('articles.export.h_chat')) . '</h2>'
                . '<p class="muted">' . $esc((string) __('articles.export.chat_subtitle')) . '</p>';
            foreach ($data['chat'] as $m) {
                $who = $m['role'] === 'assistant'
                    ? (string) __('articles.export.who_assistant')
                    : (string) __('articles.export.who_user');
                $cls = $m['role'] === 'assistant' ? 'msg msg-assistant' : 'msg msg-user';
                $stamp = $m['created_at'] !== '' ? ' · ' . $m['created_at'] : '';
                $chatHtml .= '<div class="' . $cls . '">'
                    . '<div class="msg-head">' . $esc($who . $stamp) . '</div>'
                    . '<div class="msg-body">' . $br($m['content']) . '</div>'
                    . '</div>';
            }
        }

        $overall = (string) ($report['overall'] ?? '');
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body { font-family: DejaVu Sans, sans-serif; color:#1f2937; font-size:11.5px; line-height:1.55; }'
            . 'h1 { font-size:20px; margin:0 0 6px; }'
            . 'h2 { font-size:14px; margin:18px 0 6px; border-bottom:1px solid #e5e7eb; padding-bottom:4px; }'
            . 'h2.devil { border-left:3px solid #d97706; padding:4px 0 4px 8px; border-bottom:none; }'
            . 'h3 { font-size:12.5px; margin:14px 0 4px; color:#374151; text-transform:uppercase; letter-spacing:.04em; }'
            . 'p { margin:0 0 8px; }'
            . 'p.devil { background:#fffbeb; padding:8px 10px; border-left:3px solid #d97706; }'
            . '.muted { color:#6b7280; font-size:10.5px; }'
            . '.overall { font-weight:bold; background:#f3f4f6; padding:8px 10px; border-left:3px solid #4f46e5; margin:0 0 12px; }'
            . '.axis { margin:0 0 10px; }'
            . '.axis-head { display:block; }'
            . '.axis-label { font-weight:bold; }'
            . '.axis-score { float:right; font-variant-numeric:tabular-nums; }'
            . '.axis-bar { background:#e5e7eb; height:6px; border-radius:3px; overflow:hidden; margin-top:2px; }'
            . '.axis-bar-fill { display:block; height:6px; }'
            . '.axis-note { font-size:10.5px; color:#4b5563; margin:4px 0 0; }'
            . '.msg { border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px; margin:0 0 10px; }'
            . '.msg-assistant { background:#f8fafc; }'
            . '.msg-user { background:#eef2ff; }'
            . '.msg-head { font-size:10.5px; color:#4b5563; margin-bottom:4px; font-weight:bold; }'
            . '.page-break { page-break-before:always; }'
            . '</style></head><body>'
            . '<h1>' . $esc($title) . '</h1>'
            . '<p class="muted">' . $esc((string) __('articles.export.doc_subtitle')) . '</p>'
            . ($overall !== '' ? '<p class="overall">' . $br($overall) . '</p>' : '')
            . '<h2>' . $esc((string) __('articles.export.h_scores')) . '</h2>'
            . $axesHtml
            . $blocks
            . $chatHtml
            . '</body></html>';
    }

    /**
     * PhpWord doesn't honour line breaks inside a single addText call,
     * so we split the prose on blank-line boundaries and emit one paragraph
     * per chunk. Empty input is a no-op.
     */
    private static function addParagraphs(\PhpOffice\PhpWord\Element\Section $section, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $chunks = preg_split('/\n{2,}/', $text) ?: [$text];
        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $section->addText($chunk);
            }
        }
    }
}
