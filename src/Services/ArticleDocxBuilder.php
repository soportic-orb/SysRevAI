<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\ArticleCriticalReport;

/**
 * Hand-rolled Word2007 (.docx) writer for the article critical-report
 * export. Bypasses PhpWord entirely because its 1.x line emits
 * paragraph / run / style child elements in insertion order rather
 * than the order ECMA-376 mandates, which Microsoft Word rejects.
 *
 * This builder emits document.xml, styles.xml and numbering.xml with
 * every <w:pPr>, <w:rPr> and <w:style> child in canonical schema
 * order. Output passes through Word, LibreOffice and Google Docs.
 *
 * Public entry point: {@see self::build()} returns the .docx bytes
 * for the same `$data` payload that ArticleExportService builds.
 */
final class ArticleDocxBuilder
{
    private const NS_W   = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
    private const NS_R   = 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    private const C_TEXT     = '1F2937';
    private const C_MUTED    = '4B5563';
    private const C_ACCENT   = '4F46E5';
    private const C_DEVIL    = 'D97706';
    private const C_OK       = '0A7D4D';
    private const C_WARN     = 'A16207';
    private const C_FAIL     = 'B91C1C';
    private const C_BG_GREY  = 'F3F4F6';
    private const C_BG_DEVIL = 'FFFBEB';

    /** Accumulator of `<w:body>` paragraph XML strings. */
    private string $body = '';

    /** Language the report was generated in ('' = current UI locale) —
     *  every __in() label lookup below pins to it so the document's
     *  headings match its prose. */
    private string $lang = '';

    /**
     * @param array{
     *   article: array<string,mixed>,
     *   report: ?array<string,mixed>,
     *   reportRow: ?array<string,mixed>,
     *   chat: list<array{role:string,content:string,created_at:string}>,
     *   include_chat: bool
     * } $data
     */
    public static function build(array $data): string
    {
        $builder = new self();
        return $builder->buildInternal($data);
    }

    private function buildInternal(array $data): string
    {
        $article = (array) $data['article'];
        $report  = (array) ($data['report'] ?? []);
        $this->lang = (string) ($report['language'] ?? '');

        // Header block.
        $this->addParagraph(
            self::escape((string) ($article['title'] ?: '—')),
            rPr: ['rFonts' => 'Calibri', 'b' => true, 'color' => self::C_TEXT, 'sz' => 44],
            pPr: ['spacing' => ['after' => 60]],
        );
        $this->addParagraph(
            self::escape((string) __in($this->lang, 'articles.export.doc_subtitle')),
            rPr: ['rFonts' => 'Calibri', 'i' => true, 'color' => self::C_MUTED, 'sz' => 22],
            pPr: ['spacing' => ['after' => 80]],
        );
        if (($data['reportRow'] ?? null) !== null && !empty($data['reportRow']['updated_at'])) {
            $this->addParagraph(
                self::escape((string) __in($this->lang, 'articles.critical.generated_at', (string) $data['reportRow']['updated_at'])),
                rPr: ['rFonts' => 'Calibri', 'color' => self::C_MUTED, 'sz' => 20],
                pPr: ['spacing' => ['after' => 200]],
            );
        }

        // Overall verdict — indigo callout.
        if (!empty($report['overall'])) {
            $this->addCallout((string) $report['overall'], self::C_BG_GREY, self::C_ACCENT, bold: true);
        }

        // Scores.
        $this->addHeading2((string) __in($this->lang, 'articles.export.h_scores'));
        $this->addScores($report);

        if (!empty($report['summary'])) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_summary'));
            $this->addProse((string) $report['summary']);
        }

        $strengths  = (array) ($report['key_strengths']  ?? []);
        $weaknesses = (array) ($report['key_weaknesses'] ?? []);
        if ($strengths !== []) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_key_strengths'));
            $this->addBullets($strengths, self::C_OK);
        }
        if ($weaknesses !== []) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_key_weaknesses'));
            $this->addBullets($weaknesses, self::C_FAIL);
        }

        if (!empty($report['methodology_critique'])) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_methodology_critique'));
            $this->addProse((string) $report['methodology_critique']);
        }

        foreach ([
            ['h_statistical_concerns', (array) ($report['statistical_concerns']  ?? [])],
            ['h_ethical_concerns',     (array) ($report['ethical_concerns']      ?? [])],
            ['h_reproducibility',      (array) ($report['reproducibility_notes'] ?? [])],
        ] as [$key, $items]) {
            if ($items === []) {
                continue;
            }
            $this->addHeading2((string) __in($this->lang, 'articles.critical.' . $key));
            $this->addBullets($items, self::C_TEXT);
        }

        if (!empty($report['literature_positioning'])) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_literature_positioning'));
            $this->addProse((string) $report['literature_positioning']);
        }
        if (!empty($report['publication_outlook'])) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_publication_outlook'));
            $this->addProse((string) $report['publication_outlook']);
        }

        if (!empty($report['devils_advocate'])) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_devils_advocate'));
            $this->addCallout((string) $report['devils_advocate'], self::C_BG_DEVIL, self::C_DEVIL, bold: false);
        }

        // Recommendations — one per row with coloured priority prefix.
        $recs = (array) ($report['recommendations'] ?? []);
        if ($recs !== []) {
            $this->addHeading2((string) __in($this->lang, 'articles.critical.h_recommendations'));
            foreach ($recs as $rec) {
                $sec = trim((string) ($rec['section'] ?? ''));
                if ($sec !== '') {
                    $this->addHeading3($sec);
                }
                foreach ((array) ($rec['items'] ?? []) as $it) {
                    $this->addRecommendation($it);
                }
            }
        }

        // Chat appendix.
        if (($data['include_chat'] ?? false) && ($data['chat'] ?? []) !== []) {
            $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
            $this->addHeading2((string) __in($this->lang, 'articles.export.h_chat'));
            $this->addParagraph(
                self::escape((string) __in($this->lang, 'articles.export.chat_subtitle')),
                rPr: ['rFonts' => 'Calibri', 'i' => true, 'color' => self::C_MUTED, 'sz' => 20],
                pPr: ['spacing' => ['after' => 160]],
            );
            foreach ($data['chat'] as $m) {
                $who = $m['role'] === 'assistant'
                    ? (string) __in($this->lang, 'articles.export.who_assistant')
                    : (string) __in($this->lang, 'articles.export.who_user');
                $stamp = $m['created_at'] !== '' ? ' · ' . $m['created_at'] : '';
                $this->addParagraph(
                    self::escape($who . $stamp),
                    rPr: ['rFonts' => 'Calibri', 'b' => true, 'color' => self::C_MUTED, 'sz' => 20],
                    pPr: ['spacing' => ['before' => 160, 'after' => 40]],
                );
                $this->addProse((string) $m['content']);
            }
        }

        // Final sectPr — A4 portrait with 1200-twip margins.
        $this->body .= '<w:sectPr>'
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1200" w:right="1200" w:bottom="1200" w:left="1200" w:header="0" w:footer="0" w:gutter="0"/>'
            . '</w:sectPr>';

        return $this->packDocx();
    }

    /* ── Building blocks ────────────────────────────────────────────────── */

    /**
     * Emit one paragraph. `$rPr` and `$pPr` are unordered arrays of
     * properties; serialisation happens in canonical OOXML order so
     * Word can't complain about the layout.
     *
     * @param array<string,mixed> $rPr
     * @param array<string,mixed> $pPr
     */
    private function addParagraph(string $escapedText, array $rPr = [], array $pPr = []): void
    {
        $this->body .= '<w:p>'
            . $this->renderPPr($pPr)
            . '<w:r>'
            . $this->renderRPr($rPr)
            . '<w:t xml:space="preserve">' . $escapedText . '</w:t>'
            . '</w:r>'
            . '</w:p>';
    }

    /** Emit a heading-2 paragraph (bold, bottom border). */
    private function addHeading2(string $label): void
    {
        $this->addParagraph(
            self::escape($label),
            rPr: ['rFonts' => 'Calibri', 'b' => true, 'color' => self::C_TEXT, 'sz' => 30],
            pPr: [
                'pBdr'    => ['bottom' => ['size' => 6, 'color' => 'E5E7EB']],
                'spacing' => ['before' => 360, 'after' => 140],
            ],
        );
    }

    /** Emit a heading-3 paragraph (smaller, muted, uppercase look via caps). */
    private function addHeading3(string $label): void
    {
        $this->addParagraph(
            self::escape($label),
            rPr: ['rFonts' => 'Calibri', 'b' => true, 'caps' => true, 'color' => self::C_MUTED, 'sz' => 24],
            pPr: ['spacing' => ['before' => 240, 'after' => 100]],
        );
    }

    /**
     * Emit a multi-paragraph block, splitting on blank lines so the
     * model's hand-written paragraph breaks survive.
     */
    private function addProse(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        foreach (preg_split('/\n{2,}/', $text) ?: [$text] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $this->addParagraph(
                    self::escape($chunk),
                    rPr: ['rFonts' => 'Calibri', 'color' => self::C_TEXT, 'sz' => 22],
                    pPr: ['spacing' => ['after' => 140, 'line' => 312, 'lineRule' => 'auto']],
                );
            }
        }
    }

    /** Shaded callout: paragraph with left border + background. */
    private function addCallout(string $text, string $bgHex, string $borderHex, bool $bold): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $rPr = ['rFonts' => 'Calibri', 'color' => self::C_TEXT, 'sz' => 22];
        if ($bold) {
            $rPr['b'] = true;
        }
        $pPr = [
            'pBdr'    => ['left' => ['size' => 24, 'color' => $borderHex]],
            'shd'     => ['fill' => $bgHex],
            'spacing' => ['before' => 80, 'after' => 180],
            'ind'     => ['left' => 180, 'right' => 100],
        ];
        foreach (preg_split('/\n{2,}/', $text) ?: [$text] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $this->addParagraph(self::escape($chunk), $rPr, $pPr);
            }
        }
    }

    /** Bullet list (uses numId=1 defined in numbering.xml). */
    private function addBullets(array $items, string $colourHex): void
    {
        foreach ($items as $it) {
            $text = trim((string) $it);
            if ($text === '') {
                continue;
            }
            $this->addParagraph(
                self::escape($text),
                rPr: ['rFonts' => 'Calibri', 'color' => $colourHex, 'sz' => 22],
                pPr: [
                    'numPr'   => ['ilvl' => 0, 'numId' => 1],
                    'spacing' => ['after' => 60],
                    'ind'     => ['left' => 360, 'hanging' => 360],
                ],
            );
        }
    }

    /** Score row: bold label + 10-block bar in axis tone + bold score. */
    private function addScores(array $report): void
    {
        foreach (ArticleCriticalReport::AXES as $axis) {
            $score = max(0, min(100, (int) ($report[$axis] ?? 0)));
            $note  = (string) ($report[$axis . '_note'] ?? '');
            $tone  = $score >= 80 ? self::C_OK : ($score >= 50 ? self::C_WARN : self::C_FAIL);
            $label = (string) __in($this->lang, 'articles.critical.axis_' . $axis);

            $filled = (int) round($score / 10);
            $filled = max(0, min(10, $filled));
            $bar = str_repeat("\u{2588}", $filled) . str_repeat("\u{2591}", 10 - $filled);

            $this->body .= '<w:p>'
                . $this->renderPPr([
                    'keepNext' => true,
                    'spacing'  => ['before' => 120, 'after' => 60],
                ])
                // Label
                . '<w:r>'
                . $this->renderRPr(['rFonts' => 'Calibri', 'b' => true, 'color' => self::C_TEXT, 'sz' => 24])
                . '<w:t xml:space="preserve">' . self::escape($label . '  ') . '</w:t>'
                . '</w:r>'
                // Bar
                . '<w:r>'
                . $this->renderRPr(['rFonts' => 'Consolas', 'color' => $tone, 'sz' => 22])
                . '<w:t xml:space="preserve">' . self::escape($bar . '  ') . '</w:t>'
                . '</w:r>'
                // Score
                . '<w:r>'
                . $this->renderRPr(['rFonts' => 'Calibri', 'b' => true, 'color' => $tone, 'sz' => 24])
                . '<w:t xml:space="preserve">' . self::escape($score . ' / 100') . '</w:t>'
                . '</w:r>'
                . '</w:p>';

            if ($note !== '') {
                foreach (preg_split('/\n{2,}/', trim($note)) ?: [$note] as $chunk) {
                    $chunk = trim((string) $chunk);
                    if ($chunk !== '') {
                        $this->addParagraph(
                            self::escape($chunk),
                            rPr: ['rFonts' => 'Calibri', 'color' => self::C_MUTED, 'sz' => 21],
                            pPr: ['spacing' => ['after' => 100, 'line' => 288, 'lineRule' => 'auto'], 'ind' => ['left' => 240]],
                        );
                    }
                }
            }
        }
    }

    private function addRecommendation(mixed $raw): void
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
            'high'   => self::C_FAIL,
            'low'    => '3730A3',
            default  => self::C_WARN,
        };
        $label = mb_strtoupper((string) __in($this->lang, 'articles.critical.priority_' . $priority));

        $this->body .= '<w:p>'
            . $this->renderPPr([
                'spacing' => ['after' => 80],
                'ind'     => ['left' => 240],
            ])
            . '<w:r>'
            . $this->renderRPr(['rFonts' => 'Calibri', 'b' => true, 'color' => $colour, 'sz' => 20])
            . '<w:t xml:space="preserve">' . self::escape($label . '  ') . '</w:t>'
            . '</w:r>'
            . '<w:r>'
            . $this->renderRPr(['rFonts' => 'Calibri', 'color' => self::C_TEXT, 'sz' => 22])
            . '<w:t xml:space="preserve">' . self::escape($text) . '</w:t>'
            . '</w:r>'
            . '</w:p>';
    }

    /* ── PPr / RPr serialisation in canonical order ─────────────────────── */

    /** @param array<string,mixed> $p */
    private function renderPPr(array $p): string
    {
        if ($p === []) {
            return '';
        }
        $out = '';
        // Canonical CT_PPrBase order — only emit subsets we use.
        if (!empty($p['keepNext']))   { $out .= '<w:keepNext/>'; }
        if (isset($p['numPr'])) {
            $n = $p['numPr'];
            $out .= '<w:numPr>'
                . '<w:ilvl w:val="' . (int) ($n['ilvl'] ?? 0) . '"/>'
                . '<w:numId w:val="' . (int) ($n['numId'] ?? 1) . '"/>'
                . '</w:numPr>';
        }
        if (isset($p['pBdr'])) {
            $out .= '<w:pBdr>';
            foreach (['top', 'left', 'bottom', 'right'] as $side) {
                if (!isset($p['pBdr'][$side])) {
                    continue;
                }
                $b = $p['pBdr'][$side];
                $out .= '<w:' . $side . ' w:val="single"'
                    . ' w:sz="' . (int) ($b['size'] ?? 6) . '"'
                    . ' w:space="0"'
                    . ' w:color="' . self::escapeAttr((string) ($b['color'] ?? '000000')) . '"'
                    . '/>';
            }
            $out .= '</w:pBdr>';
        }
        if (isset($p['shd'])) {
            $out .= '<w:shd w:val="clear" w:color="auto" w:fill="'
                . self::escapeAttr((string) ($p['shd']['fill'] ?? 'auto')) . '"/>';
        }
        if (isset($p['spacing'])) {
            $sp = $p['spacing'];
            $out .= '<w:spacing';
            if (isset($sp['before']))   { $out .= ' w:before="' . (int) $sp['before'] . '"'; }
            if (isset($sp['after']))    { $out .= ' w:after="' . (int) $sp['after'] . '"'; }
            if (isset($sp['line']))     { $out .= ' w:line="' . (int) $sp['line'] . '"'; }
            if (isset($sp['lineRule'])) { $out .= ' w:lineRule="' . self::escapeAttr((string) $sp['lineRule']) . '"'; }
            $out .= '/>';
        }
        if (isset($p['ind'])) {
            $i = $p['ind'];
            $out .= '<w:ind';
            if (isset($i['left']))    { $out .= ' w:left="' . (int) $i['left'] . '"'; }
            if (isset($i['right']))   { $out .= ' w:right="' . (int) $i['right'] . '"'; }
            if (isset($i['hanging'])) { $out .= ' w:hanging="' . (int) $i['hanging'] . '"'; }
            $out .= '/>';
        }
        return $out !== '' ? '<w:pPr>' . $out . '</w:pPr>' : '';
    }

    /** @param array<string,mixed> $r */
    private function renderRPr(array $r): string
    {
        if ($r === []) {
            return '';
        }
        $out = '';
        // Canonical CT_RPrBase order — only emit subsets we use.
        if (isset($r['rFonts'])) {
            $f = self::escapeAttr((string) $r['rFonts']);
            $out .= '<w:rFonts w:ascii="' . $f . '" w:hAnsi="' . $f . '" w:eastAsia="' . $f . '" w:cs="' . $f . '"/>';
        }
        if (!empty($r['b']))         { $out .= '<w:b/><w:bCs/>'; }
        if (!empty($r['i']))         { $out .= '<w:i/><w:iCs/>'; }
        if (!empty($r['caps']))      { $out .= '<w:caps/>'; }
        if (!empty($r['smallCaps'])) { $out .= '<w:smallCaps/>'; }
        if (isset($r['color']))      { $out .= '<w:color w:val="' . self::escapeAttr((string) $r['color']) . '"/>'; }
        if (isset($r['sz']))         { $out .= '<w:sz w:val="' . (int) $r['sz'] . '"/><w:szCs w:val="' . (int) $r['sz'] . '"/>'; }
        return $out !== '' ? '<w:rPr>' . $out . '</w:rPr>' : '';
    }

    /* ── ZIP packaging ──────────────────────────────────────────────────── */

    private function packDocx(): string
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'docx_');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            return '';
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('docProps/core.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Critical report</dc:title>'
            . '<dc:creator>SysRevAI</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>'
        );

        $zip->addFromString('docProps/app.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
            . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>SysRevAI</Application>'
            . '</Properties>'
        );

        $zip->addFromString('word/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles ' . self::NS_W . ' ' . self::NS_R . '>'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/>'
            . '<w:color w:val="000000"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="en-US"/>'
            . '</w:rPr></w:rPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            . '</w:styles>'
        );

        // Minimal bullet numbering: numId=1 → abstractNumId=1, single
        // bullet level using Symbol font. Word's own bullet-list look.
        $zip->addFromString('word/numbering.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:numbering ' . self::NS_W . '>'
            . '<w:abstractNum w:abstractNumId="1">'
            . '<w:multiLevelType w:val="hybridMultilevel"/>'
            . '<w:lvl w:ilvl="0">'
            . '<w:start w:val="1"/>'
            . '<w:numFmt w:val="bullet"/>'
            . '<w:lvlText w:val="&#8226;"/>'
            . '<w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:cs="Symbol" w:hint="default"/></w:rPr>'
            . '</w:lvl>'
            . '</w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num>'
            . '</w:numbering>'
        );

        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document ' . self::NS_W . ' ' . self::NS_R . '>'
            . '<w:body>' . $this->body . '</w:body>'
            . '</w:document>'
        );

        $zip->close();
        $bytes = (string) @file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    /** Escape `<w:t>` content. */
    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Escape attribute value. Same encoder; kept distinct for clarity. */
    private static function escapeAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
