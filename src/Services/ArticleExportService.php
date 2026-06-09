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
 * {@see self::collect()} and emit the same sections — score table,
 * executive summary, strengths / weaknesses, methodology critique,
 * statistical / ethical / reproducibility concerns, literature
 * positioning, publication outlook, devil's advocate and
 * section-by-section recommendations — so the two output formats stay
 * in sync with the web view.
 */
final class ArticleExportService
{
    public const SCOPE_REPORT      = 'report';
    public const SCOPE_REPORT_CHAT = 'report_chat';

    /** Tone palette used by both writers so the colour vocabulary matches. */
    private const COLOUR_TEXT      = '1F2937';
    private const COLOUR_MUTED     = '4B5563';
    private const COLOUR_BORDER    = 'E5E7EB';
    private const COLOUR_BG_LIGHT  = 'F3F4F6';
    private const COLOUR_BG_DEVIL  = 'FFFBEB';
    private const COLOUR_BG_GOOD   = 'ECFDF5';
    private const COLOUR_BG_BAD    = 'FEF2F2';
    private const COLOUR_ACCENT    = '4F46E5';
    private const COLOUR_OK        = '0A7D4D';
    private const COLOUR_WARN      = 'A16207';
    private const COLOUR_FAIL      = 'B91C1C';
    private const COLOUR_DEVIL     = 'D97706';

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

    /* ── DOCX ──────────────────────────────────────────────────────────── */

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
        $doc->setDefaultFontName('Calibri');
        $doc->setDefaultFontSize(11);
        $doc->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('es-ES'));

        // Heading styles — used by addTitle($text, $level).
        $doc->addTitleStyle(1, ['name' => 'Calibri', 'size' => 24, 'bold' => true, 'color' => self::COLOUR_TEXT]);
        $doc->addTitleStyle(2, ['name' => 'Calibri', 'size' => 15, 'bold' => true, 'color' => self::COLOUR_TEXT],
            ['spaceBefore' => 360, 'spaceAfter' => 140, 'borderBottomSize' => 6, 'borderBottomColor' => self::COLOUR_BORDER]);
        $doc->addTitleStyle(3, ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => self::COLOUR_MUTED, 'allCaps' => true],
            ['spaceBefore' => 240, 'spaceAfter' => 100]);

        // Numbered list style for recommendations.
        $doc->addNumberingStyle('recList', [
            'type' => 'multilevel',
            'levels' => [['format' => 'bullet', 'text' => "\u{2022}", 'left' => 360, 'hanging' => 360]],
        ]);

        $section = $doc->addSection([
            'marginTop' => 1200, 'marginBottom' => 1200,
            'marginLeft' => 1200, 'marginRight' => 1200,
        ]);

        $article = $data['article'];
        $report  = $data['report'];

        // Header block: article title + subtitle + meta line.
        $section->addText(
            (string) ($article['title'] ?: '—'),
            ['name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => self::COLOUR_TEXT],
            ['spaceAfter' => 80]
        );
        $section->addText(
            (string) __('articles.export.doc_subtitle'),
            ['name' => 'Calibri', 'size' => 11, 'color' => self::COLOUR_MUTED, 'italic' => true],
            ['spaceAfter' => 160]
        );
        if (($data['reportRow'] ?? null) !== null && !empty($data['reportRow']['updated_at'])) {
            $section->addText(
                (string) __('articles.critical.generated_at', (string) $data['reportRow']['updated_at']),
                ['name' => 'Calibri', 'size' => 10, 'color' => self::COLOUR_MUTED],
                ['spaceAfter' => 240]
            );
        }

        // Overall verdict — shaded callout.
        if (!empty($report['overall'])) {
            self::docxCallout($section, (string) $report['overall'], self::COLOUR_BG_LIGHT, self::COLOUR_ACCENT, true);
        }

        // Scores — heading + score table.
        $section->addTitle((string) __('articles.export.h_scores'), 2);
        self::docxScoreTable($section, $report);

        // Executive summary.
        if (!empty($report['summary'])) {
            $section->addTitle((string) __('articles.critical.h_summary'), 2);
            self::docxProse($section, (string) $report['summary']);
        }

        // Strengths / weaknesses — side-by-side coloured boxes.
        $strengths  = (array) ($report['key_strengths']  ?? []);
        $weaknesses = (array) ($report['key_weaknesses'] ?? []);
        if ($strengths !== [] || $weaknesses !== []) {
            $section->addTitle((string) __('articles.critical.h_key_strengths') . ' / ' . __('articles.critical.h_key_weaknesses'), 2);
            self::docxTwoColumnBullets(
                $section,
                (string) __('articles.critical.h_key_strengths'),  $strengths,  self::COLOUR_OK,   self::COLOUR_BG_GOOD,
                (string) __('articles.critical.h_key_weaknesses'), $weaknesses, self::COLOUR_FAIL, self::COLOUR_BG_BAD
            );
        }

        // Methodology critique.
        if (!empty($report['methodology_critique'])) {
            $section->addTitle((string) __('articles.critical.h_methodology_critique'), 2);
            self::docxProse($section, (string) $report['methodology_critique']);
        }

        // Statistical / ethical / reproducibility concerns.
        foreach ([
            ['h_statistical_concerns', $report['statistical_concerns']  ?? []],
            ['h_ethical_concerns',     $report['ethical_concerns']      ?? []],
            ['h_reproducibility',      $report['reproducibility_notes'] ?? []],
        ] as [$key, $items]) {
            $items = (array) $items;
            if ($items === []) {
                continue;
            }
            $section->addTitle((string) __('articles.critical.' . $key), 2);
            foreach ($items as $it) {
                $section->addListItem(trim((string) $it), 0,
                    ['name' => 'Calibri', 'size' => 11],
                    'recList',
                    ['spaceAfter' => 60]
                );
            }
        }

        // Literature positioning + publication outlook.
        if (!empty($report['literature_positioning'])) {
            $section->addTitle((string) __('articles.critical.h_literature_positioning'), 2);
            self::docxProse($section, (string) $report['literature_positioning']);
        }
        if (!empty($report['publication_outlook'])) {
            $section->addTitle((string) __('articles.critical.h_publication_outlook'), 2);
            self::docxProse($section, (string) $report['publication_outlook']);
        }

        // Devil's advocate — amber callout.
        if (!empty($report['devils_advocate'])) {
            $section->addTitle((string) __('articles.critical.h_devils_advocate'), 2);
            self::docxCallout($section, (string) $report['devils_advocate'], self::COLOUR_BG_DEVIL, self::COLOUR_DEVIL, false);
        }

        // Section-by-section recommendations.
        $recs = (array) ($report['recommendations'] ?? []);
        if ($recs !== []) {
            $section->addTitle((string) __('articles.critical.h_recommendations'), 2);
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $section->addTitle($sec, 3);
                }
                foreach ((array) ($rec['items'] ?? []) as $it) {
                    self::docxRecommendation($section, $it);
                }
            }
        }

        // Chat transcript appendix.
        if ($data['include_chat'] && $data['chat'] !== []) {
            $section->addPageBreak();
            $section->addTitle((string) __('articles.export.h_chat'), 2);
            $section->addText(
                (string) __('articles.export.chat_subtitle'),
                ['size' => 10, 'color' => self::COLOUR_MUTED, 'italic' => true],
                ['spaceAfter' => 160]
            );
            foreach ($data['chat'] as $m) {
                $who = $m['role'] === 'assistant'
                    ? (string) __('articles.export.who_assistant')
                    : (string) __('articles.export.who_user');
                $stamp = $m['created_at'] !== '' ? ' · ' . $m['created_at'] : '';
                $section->addText(
                    $who . $stamp,
                    ['bold' => true, 'size' => 10, 'color' => self::COLOUR_MUTED],
                    ['spaceBefore' => 160, 'spaceAfter' => 40]
                );
                self::docxProse($section, (string) $m['content']);
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
        ob_start();
        $writer->save('php://output');
        $bytes = (string) ob_get_clean();
        return ['bytes' => $bytes, 'error' => null];
    }

    /* ── DOCX building blocks ──────────────────────────────────────────── */

    /**
     * Split prose on blank-line boundaries and emit one paragraph per
     * chunk. PhpWord renders \n inside a single addText as a literal
     * space, so chunks are how we preserve the model's paragraph breaks.
     */
    private static function docxProse(\PhpOffice\PhpWord\Element\Section $section, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $fontStyle = ['name' => 'Calibri', 'size' => 11, 'color' => self::COLOUR_TEXT];
        $paraStyle = ['spaceAfter' => 140, 'lineHeight' => 1.35];
        foreach (preg_split('/\n{2,}/', $text) ?: [$text] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $section->addText($chunk, $fontStyle, $paraStyle);
            }
        }
    }

    /** Shaded, left-bordered callout paragraph used for overall + devil's-advocate. */
    private static function docxCallout(\PhpOffice\PhpWord\Element\Section $section, string $text, string $bgHex, string $borderHex, bool $bold): void
    {
        $paraStyle = [
            'shading'          => ['fill' => $bgHex],
            'borderLeftSize'   => 24,
            'borderLeftColor'  => $borderHex,
            'spaceBefore'      => 80,
            'spaceAfter'       => 180,
            'indentation'      => ['left' => 180, 'right' => 100],
        ];
        $fontStyle = ['name' => 'Calibri', 'size' => 11, 'bold' => $bold, 'color' => self::COLOUR_TEXT];
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [$text] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $section->addText($chunk, $fontStyle, $paraStyle);
            }
        }
    }

    /**
     * Score table — one row per axis: label, visual bar, score badge.
     * Multi-paragraph analysis sits on a follow-up row spanning all
     * three columns. Cell widths are in twips (1440 = 1 inch); the
     * three columns sum to ~8000 twips, which fits the default A4
     * usable width.
     */
    private static function docxScoreTable(\PhpOffice\PhpWord\Element\Section $section, array $report): void
    {
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);

        $labelW = 2400;
        $barW   = 4400;
        $scoreW = 1400;
        $barInnerTotal = $barW - 160; // leave room for cell margins

        foreach (ArticleCriticalReport::AXES as $axis) {
            $score = max(0, min(100, (int) ($report[$axis] ?? 0)));
            $note  = (string) ($report[$axis . '_note'] ?? '');
            $tone  = $score >= 80 ? self::COLOUR_OK : ($score >= 50 ? self::COLOUR_WARN : self::COLOUR_FAIL);
            $label = (string) __('articles.critical.axis_' . $axis);

            $row = $table->addRow(null, ['cantSplit' => true]);

            $labelCell = $row->addCell($labelW, ['valign' => 'center', 'borderTopSize' => 6, 'borderTopColor' => self::COLOUR_BORDER]);
            $labelCell->addText($label, ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => self::COLOUR_TEXT], ['spaceAfter' => 0]);

            $barCell = $row->addCell($barW, ['valign' => 'center', 'borderTopSize' => 6, 'borderTopColor' => self::COLOUR_BORDER]);
            $barTable = $barCell->addTable(['borderSize' => 0, 'cellMargin' => 0]);
            $barRow = $barTable->addRow(140);
            $filled = max(80, (int) round($score / 100 * $barInnerTotal));
            $empty  = max(0, $barInnerTotal - $filled);
            $filledCell = $barRow->addCell($filled, ['bgColor' => $tone]);
            $filledCell->addText(' ', ['size' => 1], ['spaceAfter' => 0]);
            if ($empty > 0) {
                $emptyCell = $barRow->addCell($empty, ['bgColor' => self::COLOUR_BORDER]);
                $emptyCell->addText(' ', ['size' => 1], ['spaceAfter' => 0]);
            }

            $scoreCell = $row->addCell($scoreW, ['valign' => 'center', 'borderTopSize' => 6, 'borderTopColor' => self::COLOUR_BORDER]);
            $scoreCell->addText($score . ' / 100', ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => $tone], ['alignment' => 'right', 'spaceAfter' => 0]);

            if ($note !== '') {
                $noteRow  = $table->addRow(null, ['cantSplit' => true]);
                $noteCell = $noteRow->addCell(null, ['gridSpan' => 3]);
                foreach (preg_split('/\n{2,}/', trim($note)) ?: [$note] as $chunk) {
                    $chunk = trim((string) $chunk);
                    if ($chunk !== '') {
                        $noteCell->addText(
                            $chunk,
                            ['name' => 'Calibri', 'size' => 10.5, 'color' => self::COLOUR_MUTED],
                            ['spaceAfter' => 100, 'lineHeight' => 1.3]
                        );
                    }
                }
            }
        }
    }

    /**
     * Side-by-side strengths / weaknesses table. Each column has its own
     * tinted background and coloured top stripe so it reads as a card,
     * mirroring the web view.
     */
    private static function docxTwoColumnBullets(
        \PhpOffice\PhpWord\Element\Section $section,
        string $leftTitle, array $leftItems, string $leftColour, string $leftBg,
        string $rightTitle, array $rightItems, string $rightColour, string $rightBg
    ): void {
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 160]);
        $row = $table->addRow(null, ['cantSplit' => true]);

        $renderCol = static function (
            \PhpOffice\PhpWord\Element\Cell $cell,
            string $title,
            array $items,
            string $colour
        ): void {
            $cell->addText(
                $title,
                ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => $colour, 'allCaps' => true],
                ['spaceAfter' => 100]
            );
            foreach ($items as $it) {
                $cell->addListItem(trim((string) $it), 0,
                    ['name' => 'Calibri', 'size' => 11, 'color' => self::COLOUR_TEXT],
                    null,
                    ['spaceAfter' => 60]
                );
            }
        };

        $leftCell = $row->addCell(4100, [
            'valign' => 'top',
            'bgColor' => $leftBg,
            'borderTopSize' => 18, 'borderTopColor' => $leftColour,
        ]);
        $renderCol($leftCell, $leftTitle, $leftItems, $leftColour);

        $rightCell = $row->addCell(4100, [
            'valign' => 'top',
            'bgColor' => $rightBg,
            'borderTopSize' => 18, 'borderTopColor' => $rightColour,
        ]);
        $renderCol($rightCell, $rightTitle, $rightItems, $rightColour);
    }

    /** A single recommendation: priority badge then text on the same row. */
    private static function docxRecommendation(\PhpOffice\PhpWord\Element\Section $section, mixed $raw): void
    {
        if (is_array($raw)) {
            $text     = trim((string) ($raw['text'] ?? ''));
            $priority = (string) ($raw['priority'] ?? 'medium');
        } else {
            $text     = trim((string) $raw);
            $priority = 'medium';
        }
        if ($text === '') {
            return;
        }
        $colour = match ($priority) {
            'high'   => self::COLOUR_FAIL,
            'low'    => '3730A3',
            default  => self::COLOUR_WARN,
        };
        $bg = match ($priority) {
            'high'   => 'FEE2E2',
            'low'    => 'E0E7FF',
            default  => 'FEF3C7',
        };
        $label = (string) __('articles.critical.priority_' . $priority);

        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);
        $row = $table->addRow(null, ['cantSplit' => true]);

        $badgeCell = $row->addCell(1100, ['valign' => 'top', 'bgColor' => $bg]);
        $badgeCell->addText(
            strtoupper($label),
            ['name' => 'Calibri', 'size' => 8.5, 'bold' => true, 'color' => $colour],
            ['alignment' => 'center', 'spaceAfter' => 0]
        );

        $bodyCell = $row->addCell(7100, ['valign' => 'top']);
        $bodyCell->addText($text, ['name' => 'Calibri', 'size' => 11, 'color' => self::COLOUR_TEXT], ['spaceAfter' => 0]);
    }

    /* ── PDF ───────────────────────────────────────────────────────────── */

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
        $report  = (array) ($data['report'] ?? []);
        $article = (array) ($data['article'] ?? []);
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $prose = static function (string $text) use ($esc): string {
            $out = '';
            foreach (preg_split('/\n{2,}/', trim($text)) ?: [$text] as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk !== '') {
                    $out .= '<p>' . nl2br($esc($chunk)) . '</p>';
                }
            }
            return $out;
        };

        $title = (string) ($article['title'] ?: '—');
        $generatedAt = '';
        if (($data['reportRow'] ?? null) !== null && !empty($data['reportRow']['updated_at'])) {
            $generatedAt = (string) __('articles.critical.generated_at', (string) $data['reportRow']['updated_at']);
        }
        $overall = (string) ($report['overall'] ?? '');

        $axesHtml = '<table class="scores"><tbody>';
        foreach (ArticleCriticalReport::AXES as $axis) {
            $score = max(0, min(100, (int) ($report[$axis] ?? 0)));
            $note  = (string) ($report[$axis . '_note'] ?? '');
            $label = (string) __('articles.critical.axis_' . $axis);
            $tone  = $score >= 80 ? '#0a7d4d' : ($score >= 50 ? '#a16207' : '#b91c1c');
            $axesHtml .= '<tr class="score-row">'
                . '<td class="score-label">' . $esc($label) . '</td>'
                . '<td class="score-bar-cell">'
                . '<div class="bar"><span style="width:' . $score . '%; background:' . $tone . '"></span></div>'
                . '</td>'
                . '<td class="score-num" style="color:' . $tone . '">' . $score . ' / 100</td>'
                . '</tr>';
            if ($note !== '') {
                $axesHtml .= '<tr class="score-note-row"><td colspan="3" class="score-note">' . nl2br($esc($note)) . '</td></tr>';
            }
        }
        $axesHtml .= '</tbody></table>';

        $strengths  = (array) ($report['key_strengths']  ?? []);
        $weaknesses = (array) ($report['key_weaknesses'] ?? []);
        $swHtml = '';
        if ($strengths !== [] || $weaknesses !== []) {
            $bullets = static function (array $items) use ($esc): string {
                $out = '<ul>';
                foreach ($items as $it) {
                    $s = trim((string) $it);
                    if ($s !== '') {
                        $out .= '<li>' . $esc($s) . '</li>';
                    }
                }
                return $out . '</ul>';
            };
            $swHtml .= '<h2>' . $esc((string) __('articles.critical.h_key_strengths')) . ' / '
                . $esc((string) __('articles.critical.h_key_weaknesses')) . '</h2>'
                . '<table class="sw"><tr>';
            if ($strengths !== []) {
                $swHtml .= '<td class="sw-col sw-good">'
                    . '<div class="sw-title">' . $esc((string) __('articles.critical.h_key_strengths')) . '</div>'
                    . $bullets($strengths) . '</td>';
            }
            if ($weaknesses !== []) {
                $swHtml .= '<td class="sw-col sw-bad">'
                    . '<div class="sw-title">' . $esc((string) __('articles.critical.h_key_weaknesses')) . '</div>'
                    . $bullets($weaknesses) . '</td>';
            }
            $swHtml .= '</tr></table>';
        }

        $concernsHtml = '';
        foreach ([
            ['h_statistical_concerns',  (array) ($report['statistical_concerns']  ?? [])],
            ['h_ethical_concerns',      (array) ($report['ethical_concerns']      ?? [])],
            ['h_reproducibility',       (array) ($report['reproducibility_notes'] ?? [])],
        ] as [$key, $items]) {
            if ($items === []) {
                continue;
            }
            $concernsHtml .= '<h2>' . $esc((string) __('articles.critical.' . $key)) . '</h2><ul>';
            foreach ($items as $it) {
                $s = trim((string) $it);
                if ($s !== '') {
                    $concernsHtml .= '<li>' . $esc($s) . '</li>';
                }
            }
            $concernsHtml .= '</ul>';
        }

        $recsHtml = '';
        $recs = (array) ($report['recommendations'] ?? []);
        if ($recs !== []) {
            $recsHtml .= '<h2>' . $esc((string) __('articles.critical.h_recommendations')) . '</h2>';
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $recsHtml .= '<h3>' . $esc($sec) . '</h3>';
                }
                $items = (array) ($rec['items'] ?? []);
                if ($items !== []) {
                    $recsHtml .= '<table class="recs">';
                    foreach ($items as $it) {
                        if (is_array($it)) {
                            $text     = trim((string) ($it['text'] ?? ''));
                            $priority = (string) ($it['priority'] ?? 'medium');
                        } else {
                            $text     = trim((string) $it);
                            $priority = 'medium';
                        }
                        if ($text === '') {
                            continue;
                        }
                        $recsHtml .= '<tr>'
                            . '<td class="prio prio-' . $esc($priority) . '">'
                            . $esc((string) __('articles.critical.priority_' . $priority))
                            . '</td>'
                            . '<td class="rec-text">' . $esc($text) . '</td>'
                            . '</tr>';
                    }
                    $recsHtml .= '</table>';
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
                    . '<div class="msg-body">' . $prose($m['content']) . '</div>'
                    . '</div>';
            }
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body { font-family: DejaVu Sans, sans-serif; color:#1f2937; font-size:11.5px; line-height:1.55; }'
            . 'h1 { font-size:22px; margin:0 0 4px; }'
            . '.subtitle { font-style:italic; color:#6b7280; margin:0 0 4px; font-size:11px; }'
            . '.meta { color:#6b7280; font-size:10px; margin:0 0 16px; }'
            . 'h2 { font-size:14px; margin:18px 0 6px; border-bottom:1px solid #e5e7eb; padding-bottom:4px; color:#1f2937; }'
            . 'h2.devil { border-left:3px solid #d97706; padding:4px 0 4px 8px; border-bottom:none; }'
            . 'h3 { font-size:12px; margin:14px 0 4px; color:#374151; text-transform:uppercase; letter-spacing:.04em; }'
            . 'p { margin:0 0 8px; }'
            . 'ul { margin:0 0 8px; padding-left:18px; }'
            . 'ul li + li { margin-top:4px; }'
            . '.muted { color:#6b7280; font-size:10.5px; }'
            . '.overall { font-weight:bold; background:#f3f4f6; padding:10px 12px; border-left:3px solid #4f46e5; margin:0 0 14px; font-size:12.5px; }'
            . '.devil-callout { background:#fffbeb; padding:10px 12px; border-left:3px solid #d97706; margin:0 0 14px; }'
            . '.devil-callout p:last-child { margin:0; }'
            /* Scores table — three columns: label / bar / score. */
            . '.scores { width:100%; border-collapse:collapse; margin:0 0 8px; }'
            . '.scores tr.score-row td { border-top:1px solid #e5e7eb; padding:8px 6px; vertical-align:middle; }'
            . '.scores tr:first-child td { border-top:0; }'
            . '.score-label { width:30%; font-weight:bold; font-size:12px; }'
            . '.score-bar-cell { width:52%; }'
            . '.bar { background:#e5e7eb; height:8px; border-radius:4px; overflow:hidden; }'
            . '.bar span { display:block; height:8px; }'
            . '.score-num { width:18%; text-align:right; font-weight:bold; font-size:11.5px; }'
            . '.score-note { padding:0 6px 10px; color:#4b5563; font-size:10.5px; line-height:1.45; border-top:0; }'
            /* Strengths / weaknesses side-by-side cards. */
            . '.sw { width:100%; border-collapse:separate; border-spacing:8px 0; }'
            . '.sw-col { vertical-align:top; padding:10px 12px; border-radius:6px; }'
            . '.sw-good { background:#ecfdf5; border-top:3px solid #0a7d4d; }'
            . '.sw-bad  { background:#fef2f2; border-top:3px solid #b91c1c; }'
            . '.sw-title { text-transform:uppercase; letter-spacing:.04em; font-weight:bold; font-size:10.5px; color:#4b5563; margin:0 0 6px; }'
            /* Recommendations table — priority badge + body. */
            . '.recs { width:100%; border-collapse:separate; border-spacing:0 4px; margin:6px 0; }'
            . '.recs td { vertical-align:top; padding:6px 8px; }'
            . '.prio { width:62px; text-align:center; font-weight:bold; font-size:9px; text-transform:uppercase; letter-spacing:.06em; border-radius:999px; }'
            . '.prio-high   { background:#fee2e2; color:#b91c1c; }'
            . '.prio-medium { background:#fef3c7; color:#92400e; }'
            . '.prio-low    { background:#e0e7ff; color:#3730a3; }'
            . '.rec-text { font-size:11px; }'
            /* Chat appendix. */
            . '.msg { border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px; margin:0 0 10px; }'
            . '.msg-assistant { background:#f8fafc; }'
            . '.msg-user { background:#eef2ff; }'
            . '.msg-head { font-size:10.5px; color:#4b5563; margin-bottom:4px; font-weight:bold; }'
            . '.page-break { page-break-before:always; }'
            . '</style></head><body>'
            . '<h1>' . $esc($title) . '</h1>'
            . '<p class="subtitle">' . $esc((string) __('articles.export.doc_subtitle')) . '</p>'
            . ($generatedAt !== '' ? '<p class="meta">' . $esc($generatedAt) . '</p>' : '')
            . ($overall !== '' ? '<p class="overall">' . $esc($overall) . '</p>' : '')
            . '<h2>' . $esc((string) __('articles.export.h_scores')) . '</h2>'
            . $axesHtml
            . (!empty($report['summary']) ? '<h2>' . $esc((string) __('articles.critical.h_summary')) . '</h2>' . $prose((string) $report['summary']) : '')
            . $swHtml
            . (!empty($report['methodology_critique']) ? '<h2>' . $esc((string) __('articles.critical.h_methodology_critique')) . '</h2>' . $prose((string) $report['methodology_critique']) : '')
            . $concernsHtml
            . (!empty($report['literature_positioning']) ? '<h2>' . $esc((string) __('articles.critical.h_literature_positioning')) . '</h2>' . $prose((string) $report['literature_positioning']) : '')
            . (!empty($report['publication_outlook']) ? '<h2>' . $esc((string) __('articles.critical.h_publication_outlook')) . '</h2>' . $prose((string) $report['publication_outlook']) : '')
            . (!empty($report['devils_advocate']) ? '<h2 class="devil">' . $esc((string) __('articles.critical.h_devils_advocate')) . '</h2><div class="devil-callout">' . $prose((string) $report['devils_advocate']) . '</div>' : '')
            . $recsHtml
            . $chatHtml
            . '</body></html>';
    }
}
