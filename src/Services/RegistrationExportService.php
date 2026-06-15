<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Build Word (.docx) and PDF deliverables for the PROSPERO / OSF
 * registration form. Both formats render the same logical structure —
 * cover header (title + registry + review id + generated-at), then a
 * field-by-field "label → value" list.
 *
 * Word output goes through PhpWord. PDF output goes through Dompdf
 * with a tiny inline-styled HTML template — the same pattern already
 * used by ArticleExportService for the critical-report PDF.
 */
final class RegistrationExportService
{
    /**
     * @param array<int,array{id:string,label:string,type:string,hint?:string,rows?:int}> $schema
     * @param array<string,string>                                                        $data
     */
    public static function word(string $title, string $kind, array $schema, array $data): string
    {
        $doc = new \PhpOffice\PhpWord\PhpWord();
        $doc->setDefaultFontName('Calibri');
        $doc->setDefaultFontSize(11);

        $section = $doc->addSection([
            'marginTop'    => 1100,
            'marginBottom' => 1100,
            'marginLeft'   => 1100,
            'marginRight'  => 1100,
        ]);

        $registryLabel = $kind === RegistrationFields::KIND_OSF
            ? 'OSF Scoping Review Registration'
            : 'PROSPERO Systematic Review Registration';

        $section->addText($registryLabel, ['bold' => true, 'size' => 16, 'color' => '111111']);
        $section->addText($title, ['bold' => true, 'size' => 22]);
        $section->addText('Generated: ' . gmdate('Y-m-d H:i') . ' UTC', ['italic' => true, 'color' => '666666', 'size' => 9]);
        $section->addTextBreak(1);

        foreach ($schema as $f) {
            $label = self::labelFor($f);
            $value = trim((string) ($data[$f['id']] ?? ''));
            $section->addText($label, ['bold' => true, 'size' => 11, 'color' => '222222']);
            if ($value === '') {
                $section->addText('—', ['italic' => true, 'color' => '888888']);
            } else {
                foreach (preg_split('/\n+/', $value) ?: [$value] as $line) {
                    $line = trim((string) $line);
                    if ($line !== '') {
                        $section->addText($line);
                    }
                }
            }
            $section->addTextBreak(1);
        }

        ob_start();
        \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007')->save('php://output');
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,array{id:string,label:string,type:string,hint?:string,rows?:int}> $schema
     * @param array<string,string>                                                        $data
     */
    public static function pdf(string $title, string $kind, array $schema, array $data): string
    {
        $registryLabel = $kind === RegistrationFields::KIND_OSF
            ? 'OSF Scoping Review Registration'
            : 'PROSPERO Systematic Review Registration';

        $rows = '';
        foreach ($schema as $f) {
            $label = htmlspecialchars(self::labelFor($f), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = trim((string) ($data[$f['id']] ?? ''));
            $valueHtml = $value === ''
                ? '<span class="muted">—</span>'
                : nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $rows .= "<div class=\"field\"><div class=\"k\">{$label}</div><div class=\"v\">{$valueHtml}</div></div>";
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 28mm 22mm; }
            body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11pt; color: #1a1a1a; line-height: 1.45; }
            .registry { font-size: 11pt; font-weight: 700; color: #444; text-transform: uppercase; letter-spacing: .05em; }
            h1 { font-size: 20pt; margin: 6pt 0 4pt; }
            .meta { color: #777; font-size: 9pt; margin-bottom: 16pt; }
            .field { margin-bottom: 12pt; page-break-inside: avoid; }
            .k { font-weight: 700; margin-bottom: 2pt; color: #222; }
            .v { white-space: normal; }
            .muted { color: #888; font-style: italic; }
            hr { border: 0; border-top: 1px solid #d8d8d8; margin: 8pt 0 18pt; }
        </style></head><body>
            <div class="registry">' . htmlspecialchars($registryLabel) . '</div>
            <h1>' . htmlspecialchars($title) . '</h1>
            <div class="meta">Generated: ' . gmdate('Y-m-d H:i') . ' UTC</div>
            <hr>' . $rows . '
        </body></html>';

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /** @param array{id:string,label:string,type:string,hint?:string,rows?:int} $f */
    private static function labelFor(array $f): string
    {
        $key = 'registration.fields.' . $f['label'];
        $translated = __($key);
        // __() returns the key untouched when there's no translation,
        // so when that happens fall back to a humanised id.
        if ($translated === $key) {
            return ucfirst(str_replace('_', ' ', $f['id']));
        }
        return $translated;
    }
}
