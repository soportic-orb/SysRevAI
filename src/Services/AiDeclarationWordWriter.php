<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Word writer for the AI-usage declaration document. Produces a
 * formal-looking deliverable the researcher can attach to a paper or
 * include as supplementary material — cover, narrative paragraph,
 * per-feature table, totals row.
 */
final class AiDeclarationWordWriter
{
    public static function build(array $report): string
    {
        $review   = $report['review'];
        $features = $report['features'];
        $totals   = $report['totals'];
        $period   = $report['period'];

        $doc = new \PhpOffice\PhpWord\PhpWord();
        $doc->setDefaultFontName('Calibri');
        $doc->setDefaultFontSize(11);

        $section = $doc->addSection([
            'marginTop'    => 1100,
            'marginBottom' => 1100,
            'marginLeft'   => 1100,
            'marginRight'  => 1100,
        ]);

        // ── Header ────────────────────────────────────────────────────
        $section->addText(__('ai_declaration.doc.eyebrow'),
            ['bold' => true, 'size' => 10, 'color' => '666666']);
        $section->addText(__('ai_declaration.doc.title'),
            ['bold' => true, 'size' => 22]);
        $section->addText(sprintf(__('ai_declaration.doc.review'), (string) ($review['title'] ?? '—')),
            ['size' => 12, 'color' => '333333']);
        $section->addText(sprintf(__('ai_declaration.doc.generated'), gmdate('Y-m-d H:i') . ' UTC'),
            ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);

        // ── Narrative paragraph ──────────────────────────────────────
        $section->addText(__('ai_declaration.doc.summary_heading'),
            ['bold' => true, 'size' => 13]);
        $section->addText(self::narrativeText($report),
            ['size' => 11]);
        $section->addTextBreak(1);

        // ── Per-feature table ────────────────────────────────────────
        $section->addText(__('ai_declaration.doc.detail_heading'),
            ['bold' => true, 'size' => 13]);

        $style = ['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80];
        $doc->addTableStyle('aiDeclaration', $style, $style);
        $table = $section->addTable('aiDeclaration');

        $headStyle = ['bgColor' => 'EEEEEE'];
        $boldFont  = ['bold' => true, 'size' => 10];
        $table->addRow();
        $table->addCell(3500, $headStyle)->addText(__('ai_declaration.col_phase'), $boldFont);
        $table->addCell(4500, $headStyle)->addText(__('ai_declaration.col_description'), $boldFont);
        $table->addCell(1000, $headStyle)->addText(__('ai_declaration.col_calls'), $boldFont);
        $table->addCell(2000, $headStyle)->addText(__('ai_declaration.col_period'), $boldFont);

        foreach ($features as $f) {
            $table->addRow();
            $table->addCell(3500)->addText($f['label'], ['bold' => true, 'size' => 10]);
            $table->addCell(4500)->addText($f['description'], ['size' => 10]);
            $table->addCell(1000)->addText((string) $f['calls'], ['size' => 10]);

            $modelLine = '';
            if ($f['models'] !== []) {
                $modelLine = "\n" . __('ai_declaration.models') . ': '
                    . implode(', ', $f['models']);
            }
            $period = self::dateRange($f['first_at'] ?? null, $f['last_at'] ?? null);
            $table->addCell(2000)->addText($period . $modelLine, ['size' => 10]);
        }

        // Totals
        $table->addRow();
        $totalLabel = ['bold' => true, 'size' => 10];
        $table->addCell(3500, ['bgColor' => 'F7F7F7'])->addText(__('ai_declaration.totals'), $totalLabel);
        $table->addCell(4500, ['bgColor' => 'F7F7F7'])->addText(
            sprintf(__('ai_declaration.totals_tokens'),
                number_format((int) $totals['input_tokens']),
                number_format((int) $totals['output_tokens']),
            ),
            ['size' => 10]
        );
        $table->addCell(1000, ['bgColor' => 'F7F7F7'])->addText((string) $totals['calls'], ['size' => 10, 'bold' => true]);
        $table->addCell(2000, ['bgColor' => 'F7F7F7'])->addText(
            self::dateRange($period['first_at'] ?? null, $period['last_at'] ?? null),
            ['size' => 10]
        );

        $section->addTextBreak(1);

        // ── Closing statement ────────────────────────────────────────
        $section->addText(__('ai_declaration.doc.closing_heading'),
            ['bold' => true, 'size' => 13]);
        $section->addText(__('ai_declaration.doc.closing_body'), ['size' => 11]);

        ob_start();
        \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007')->save('php://output');
        return (string) ob_get_clean();
    }

    /** Compose the human-readable narrative that opens the document. */
    private static function narrativeText(array $report): string
    {
        $features = $report['features'];
        $review   = $report['review'];
        $totals   = $report['totals'];

        if ($features === []) {
            return __('ai_declaration.doc.summary_empty');
        }

        $phases = array_map(static fn ($f) => $f['label'], $features);
        $intro  = sprintf(
            __('ai_declaration.doc.summary_intro'),
            (string) ($review['title'] ?? '—'),
            self::joinList($phases)
        );
        $tail   = sprintf(
            __('ai_declaration.doc.summary_tail'),
            (int) $totals['calls']
        );
        return $intro . ' ' . $tail;
    }

    private static function joinList(array $items): string
    {
        $items = array_values(array_filter(array_map('strval', $items), static fn ($s) => $s !== ''));
        $n = count($items);
        if ($n === 0) return '';
        if ($n === 1) return $items[0];
        if ($n === 2) return $items[0] . ' ' . __('ai_declaration.and') . ' ' . $items[1];
        $last = array_pop($items);
        return implode(', ', $items) . ', ' . __('ai_declaration.and') . ' ' . $last;
    }

    private static function dateRange(?string $first, ?string $last): string
    {
        if ($first === null && $last === null) return '—';
        if ($first === null) return self::short($last);
        if ($last === null || $last === $first) return self::short($first);
        return self::short($first) . ' → ' . self::short($last);
    }

    private static function short(?string $ts): string
    {
        if ($ts === null) return '—';
        $t = strtotime($ts);
        return $t !== false ? date('Y-m-d', $t) : (string) $ts;
    }
}
