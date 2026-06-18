<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Core\Database;
use SysRevAI\Models\AiSummary;
use SysRevAI\Models\ExtractionData;
use SysRevAI\Models\ExtractionTemplate;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;

/**
 * Review exports: PRISMA 2020 flow diagram (SVG), CSV/Excel of references +
 * extracted fields, Word (.docx) with the protocol and the included studies,
 * and a minimal RevMan 5 (Cochrane) XML skeleton.
 */
final class ExportService
{
    /**
     * Counts used by the PRISMA 2020 flow diagram.
     * @return array<string,int>
     */
    public static function prismaCounts(int $reviewId): array
    {
        $refs = Database::table('references');
        $logs = Database::table('import_logs');

        $byStatus = [];
        foreach (Database::select(
            "SELECT status, COUNT(*) AS c FROM `{$refs}` WHERE review_id = ? GROUP BY status",
            [$reviewId]
        ) as $row) {
            $byStatus[(string) $row['status']] = (int) $row['c'];
        }

        $identifiedRow = Database::selectOne(
            "SELECT COALESCE(SUM(total_parsed), 0) AS c FROM `{$logs}` WHERE review_id = ?",
            [$reviewId]
        );
        $identified = (int) ($identifiedRow['c'] ?? 0);
        if ($identified === 0) {
            $identified = array_sum($byStatus);
        }

        // Duplicates removed = currently flagged as status='duplicate'
        // (rows still on the table) + the persistent counter populated
        // every time the user bulk-deletes a duplicate from the
        // references list. Without the counter the PRISMA cell would
        // fall to 0 the moment the user cleaned up duplicates.
        $duplicatesPersisted = 0;
        try {
            $reviews = Database::table('reviews');
            $reviewRow = Database::selectOne(
                "SELECT duplicates_removed FROM `{$reviews}` WHERE id = ?",
                [$reviewId]
            );
            $duplicatesPersisted = (int) ($reviewRow['duplicates_removed'] ?? 0);
        } catch (\Throwable) {
            // Pre-migration install — column doesn't exist yet.
        }
        $duplicates = ($byStatus['duplicate'] ?? 0) + $duplicatesPersisted;
        $afterDedup = max(0, $identified - $duplicates);
        $screenedTa = ($byStatus['ta_screening'] ?? 0)
            + ($byStatus['ta_included'] ?? 0)
            + ($byStatus['ta_excluded'] ?? 0)
            + ($byStatus['ft_screening'] ?? 0)
            + ($byStatus['ft_included'] ?? 0)
            + ($byStatus['ft_excluded'] ?? 0)
            + ($byStatus['extracted'] ?? 0);

        $computed = [
            'identified'       => $identified,
            'duplicates'       => $duplicates,
            'after_dedup'      => $afterDedup,
            'screened_ta'      => $screenedTa,
            'excluded_ta'      => $byStatus['ta_excluded'] ?? 0,
            'sought_retrieval' => ($byStatus['ta_included'] ?? 0)
                                    + ($byStatus['ft_screening'] ?? 0)
                                    + ($byStatus['ft_included'] ?? 0)
                                    + ($byStatus['ft_excluded'] ?? 0)
                                    + ($byStatus['extracted'] ?? 0),
            'assessed_ft'      => ($byStatus['ft_screening'] ?? 0)
                                    + ($byStatus['ft_included'] ?? 0)
                                    + ($byStatus['ft_excluded'] ?? 0)
                                    + ($byStatus['extracted'] ?? 0),
            'excluded_ft'      => $byStatus['ft_excluded'] ?? 0,
            'included'         => ($byStatus['ft_included'] ?? 0) + ($byStatus['extracted'] ?? 0),
        ];

        // Manual overrides (mig. 039). The user — or the Copilot via the
        // set_prisma_cells action — can pin any cell to an absolute value
        // that overrides the computed figure. A NULL / missing key falls
        // through to the computed result. If the user pinned `identified`
        // or `duplicates` but not `after_dedup`, recompute the latter so
        // the diagram stays internally consistent.
        $overrides = \SysRevAI\Models\Review::prismaOverrides($reviewId);
        $final = $computed;
        foreach ($overrides as $k => $v) {
            $final[$k] = (int) $v;
        }
        if (!array_key_exists('after_dedup', $overrides)
            && (array_key_exists('identified', $overrides) || array_key_exists('duplicates', $overrides))
        ) {
            $final['after_dedup'] = max(0, (int) $final['identified'] - (int) $final['duplicates']);
        }
        return $final;
    }

    /** Produce an SVG of the PRISMA 2020 flow with the given counts. */
    public static function prismaSvg(array $counts, string $reviewTitle = ''): string
    {
        $boxes = [
            ['x' => 40,  'y' => 30,  'w' => 240, 'h' => 70, 'title' => 'Identification',
                'text' => "Records identified\nfrom databases\n(n = " . $counts['identified'] . ")"],
            ['x' => 360, 'y' => 30,  'w' => 240, 'h' => 70, 'title' => 'Removed before screening',
                'text' => "Duplicates removed\n(n = " . $counts['duplicates'] . ")"],

            ['x' => 40,  'y' => 150, 'w' => 240, 'h' => 70, 'title' => 'Screening',
                'text' => "Records screened (T/A)\n(n = " . $counts['after_dedup'] . ")"],
            ['x' => 360, 'y' => 150, 'w' => 240, 'h' => 70, 'title' => 'Excluded',
                'text' => "Records excluded\n(n = " . $counts['excluded_ta'] . ")"],

            ['x' => 40,  'y' => 270, 'w' => 240, 'h' => 70, 'title' => 'Eligibility',
                'text' => "Reports assessed for\neligibility (full text)\n(n = " . $counts['assessed_ft'] . ")"],
            ['x' => 360, 'y' => 270, 'w' => 240, 'h' => 70, 'title' => 'Excluded',
                'text' => "Reports excluded\n(n = " . $counts['excluded_ft'] . ")"],

            ['x' => 40,  'y' => 390, 'w' => 240, 'h' => 70, 'title' => 'Included',
                'text' => "Studies included\nin review\n(n = " . $counts['included'] . ")"],
        ];

        $arrows = [
            ['from' => [160, 100], 'to' => [160, 150]],
            ['from' => [280, 65],  'to' => [360, 65]],
            ['from' => [160, 220], 'to' => [160, 270]],
            ['from' => [280, 185], 'to' => [360, 185]],
            ['from' => [160, 340], 'to' => [160, 390]],
            ['from' => [280, 305], 'to' => [360, 305]],
        ];

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 660 490" width="660" height="490">' . "\n";
        $svg .= '<style>'
            . '.box{fill:#fff;stroke:#0072b2;stroke-width:1.5}'
            . '.box--right{stroke:#5b6b7b}'
            . '.title{font:600 11px/1.2 "Inter",Arial,sans-serif;fill:#5b6b7b;text-transform:uppercase;letter-spacing:.04em}'
            . '.text{font:13px/1.35 "Inter",Arial,sans-serif;fill:#1b2733}'
            . '.arrow{stroke:#5b6b7b;stroke-width:1.5;fill:none;marker-end:url(#arr)}'
            . '.h1{font:700 16px "Inter",Arial,sans-serif;fill:#1b2733}'
            . '</style>' . "\n";
        $svg .= '<defs><marker id="arr" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">'
            . '<path d="M0,0 L9,3 L0,6 z" fill="#5b6b7b"/></marker></defs>' . "\n";

        if ($reviewTitle !== '') {
            $svg .= '<text class="h1" x="40" y="20">' . self::xml($reviewTitle) . '</text>' . "\n";
        }

        foreach ($arrows as $a) {
            $svg .= sprintf(
                '<line class="arrow" x1="%d" y1="%d" x2="%d" y2="%d"/>' . "\n",
                $a['from'][0], $a['from'][1], $a['to'][0], $a['to'][1]
            );
        }

        foreach ($boxes as $i => $b) {
            $cls = $i % 2 === 1 ? 'box box--right' : 'box';
            $svg .= sprintf(
                '<rect class="%s" x="%d" y="%d" width="%d" height="%d" rx="6"/>' . "\n",
                $cls, $b['x'], $b['y'], $b['w'], $b['h']
            );
            $svg .= sprintf(
                '<text class="title" x="%d" y="%d">%s</text>' . "\n",
                $b['x'] + 10, $b['y'] + 16, self::xml($b['title'])
            );
            $textY = $b['y'] + 34;
            foreach (explode("\n", $b['text']) as $line) {
                $svg .= sprintf(
                    '<text class="text" x="%d" y="%d">%s</text>' . "\n",
                    $b['x'] + 10, $textY, self::xml($line)
                );
                $textY += 16;
            }
        }

        $svg .= '</svg>' . "\n";
        return $svg;
    }

    /** Stream a CSV of references + extracted fields to STDOUT. */
    public static function csv(int $reviewId): void
    {
        $rows = Reference::forReview($reviewId, '', '', 1, 10000)['rows'];
        $template = ExtractionTemplate::ensureDefault($reviewId);
        $fields = ExtractionTemplate::decodeFields($template);

        $out = fopen('php://output', 'wb');
        $header = ['id', 'title', 'authors', 'year', 'journal', 'doi', 'pmid', 'status'];
        foreach ($fields as $f) {
            $header[] = 'ext_' . ($f['key'] ?? '');
        }
        fputcsv($out, $header, ',', '"', '');

        foreach ($rows as $r) {
            $authors = json_decode((string) $r['authors_json'], true) ?: [];
            $base = [
                (int) $r['id'],
                (string) ($r['title'] ?? ''),
                implode('; ', $authors),
                $r['year'] ?? '',
                (string) ($r['journal'] ?? ''),
                (string) ($r['doi'] ?? ''),
                (string) ($r['pmid'] ?? ''),
                (string) $r['status'],
            ];
            $extracted = self::firstSubmittedExtraction((int) $r['id'], (int) $template['id']);
            foreach ($fields as $f) {
                $key = (string) ($f['key'] ?? '');
                $value = $extracted[$key] ?? null;
                $base[] = is_array($value) ? implode(', ', $value) : (string) ($value ?? '');
            }
            fputcsv($out, $base, ',', '"', '');
        }
        fclose($out);
    }

    /** @return array{bytes:?string,error:?string} */
    public static function excel(int $reviewId): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return ['bytes' => null, 'error' => 'phpspreadsheet_not_installed'];
        }
        $review = Review::find($reviewId);
        $rows = Reference::forReview($reviewId, '', '', 1, 10000)['rows'];
        $template = ExtractionTemplate::ensureDefault($reviewId);
        $fields = ExtractionTemplate::decodeFields($template);

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('References');

        $header = ['id', 'title', 'authors', 'year', 'journal', 'doi', 'pmid', 'status'];
        foreach ($fields as $f) {
            $header[] = 'ext_' . ($f['key'] ?? '');
        }
        $sheet->fromArray([$header], null, 'A1');

        $r = 2;
        foreach ($rows as $row) {
            $authors = json_decode((string) $row['authors_json'], true) ?: [];
            $line = [
                (int) $row['id'],
                (string) ($row['title'] ?? ''),
                implode('; ', $authors),
                $row['year'] ?? null,
                (string) ($row['journal'] ?? ''),
                (string) ($row['doi'] ?? ''),
                (string) ($row['pmid'] ?? ''),
                (string) $row['status'],
            ];
            $extracted = self::firstSubmittedExtraction((int) $row['id'], (int) $template['id']);
            foreach ($fields as $f) {
                $key = (string) ($f['key'] ?? '');
                $value = $extracted[$key] ?? null;
                $line[] = is_array($value) ? implode(', ', $value) : ($value ?? '');
            }
            $sheet->fromArray([$line], null, 'A' . $r);
            $r++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        ob_start();
        $writer->save('php://output');
        $bytes = (string) ob_get_clean();
        return ['bytes' => $bytes, 'error' => null];
    }

    /** @return array{bytes:?string,error:?string} */
    public static function word(int $reviewId): array
    {
        if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            return ['bytes' => null, 'error' => 'phpword_not_installed'];
        }
        $review = Review::find($reviewId);
        if ($review === null) {
            return ['bytes' => null, 'error' => 'review_not_found'];
        }
        $pico = Review::pico($review);
        $rows = Reference::forReview($reviewId, '', '', 1, 10000)['rows'];

        $doc = new \PhpOffice\PhpWord\PhpWord();
        $section = $doc->addSection();
        $section->addTitle((string) $review['title'], 1);
        if (!empty($review['question'])) {
            $section->addText((string) $review['question']);
        }

        $section->addTitle('Protocol', 2);
        foreach (['population', 'intervention', 'comparison', 'outcome', 'study_design'] as $f) {
            if (!empty($pico[$f])) {
                $section->addText(ucfirst($f) . ': ' . (string) $pico[$f]);
            }
        }
        if (!empty($review['inclusion_criteria'])) {
            $section->addTitle('Inclusion criteria', 3);
            $section->addText((string) $review['inclusion_criteria']);
        }
        if (!empty($review['exclusion_criteria'])) {
            $section->addTitle('Exclusion criteria', 3);
            $section->addText((string) $review['exclusion_criteria']);
        }

        $included = array_filter($rows, static fn ($r) => in_array($r['status'], ['ft_included', 'extracted'], true));
        $section->addTitle('Included studies (' . count($included) . ')', 2);
        foreach ($included as $r) {
            $authors = json_decode((string) $r['authors_json'], true) ?: [];
            $citation = (implode('; ', array_slice($authors, 0, 3))
                . ($authors && count($authors) > 3 ? ' et al.' : '')
                . (!empty($r['year']) ? ' (' . (int) $r['year'] . ')' : '')
                . '. ' . (string) ($r['title'] ?? '')
                . (!empty($r['journal']) ? '. ' . (string) $r['journal'] : '')
                . (!empty($r['doi']) ? '. doi: ' . (string) $r['doi'] : ''));
            $section->addListItem($citation);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
        ob_start();
        $writer->save('php://output');
        $bytes = (string) ob_get_clean();
        return ['bytes' => $bytes, 'error' => null];
    }

    /** Minimal RevMan 5 (Cochrane) XML skeleton with included studies. */
    public static function revman(int $reviewId): string
    {
        try {
            $review = Review::find($reviewId);
        } catch (\Throwable) {
            $review = null;
        }
        if ($review === null) {
            return '<?xml version="1.0" encoding="UTF-8"?><COCHRANE_REVIEW/>';
        }
        try {
            $rows = Reference::forReview($reviewId, '', '', 1, 10000)['rows'];
        } catch (\Throwable) {
            $rows = [];
        }
        $included = array_filter($rows, static fn ($r) => in_array($r['status'], ['ft_included', 'extracted'], true));

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('COCHRANE_REVIEW');
        $root->setAttribute('TYPE', 'INTERVENTION');
        $doc->appendChild($root);

        $cover = $doc->createElement('COVER_SHEET');
        $cover->appendChild(self::xmlEl($doc, 'TITLE', (string) $review['title']));
        if (!empty($review['question'])) {
            $cover->appendChild(self::xmlEl($doc, 'REVIEW_QUESTION', (string) $review['question']));
        }
        $root->appendChild($cover);

        $studies = $doc->createElement('STUDIES_AND_REFERENCES');
        $included_studies = $doc->createElement('INCLUDED_STUDIES');
        foreach ($included as $r) {
            $study = $doc->createElement('STUDY');
            $study->setAttribute('ID', 'STD-' . (int) $r['id']);
            $study->appendChild(self::xmlEl($doc, 'TITLE', (string) ($r['title'] ?? '')));
            if (!empty($r['year'])) {
                $study->appendChild(self::xmlEl($doc, 'YEAR', (string) (int) $r['year']));
            }
            if (!empty($r['journal'])) {
                $study->appendChild(self::xmlEl($doc, 'SOURCE', (string) $r['journal']));
            }
            if (!empty($r['doi'])) {
                $study->appendChild(self::xmlEl($doc, 'DOI', (string) $r['doi']));
            }
            if (!empty($r['pmid'])) {
                $study->appendChild(self::xmlEl($doc, 'PMID', (string) $r['pmid']));
            }
            $included_studies->appendChild($study);
        }
        $studies->appendChild($included_studies);
        $root->appendChild($studies);

        return (string) $doc->saveXML();
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /** Pick the first submitted-or-approved extraction for a reference. */
    private static function firstSubmittedExtraction(int $referenceId, int $templateId): array
    {
        foreach (ExtractionData::forReference($referenceId) as $row) {
            if ((int) $row['template_id'] === $templateId && in_array($row['status'], ['submitted', 'approved'], true)) {
                return ExtractionData::decodeData($row);
            }
        }
        return [];
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function xmlEl(\DOMDocument $doc, string $name, string $value): \DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));
        return $el;
    }
}
