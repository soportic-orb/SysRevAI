<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * PDF text extraction via smalot/pdfparser (when the vendor folder is installed).
 *
 * Returns ['text' => string, 'pages' => int]. Without the parser the platform
 * still accepts uploads — text extraction simply yields an empty string and
 * AI features that need the body will say so.
 */
final class PdfService
{
    public static function extract(string $pdfPath): array
    {
        if (!is_file($pdfPath)) {
            return ['text' => '', 'pages' => 0];
        }
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return ['text' => '', 'pages' => 0];
        }
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $document = $parser->parseFile($pdfPath);
            $text = self::normalize($document->getText());
            $pages = count($document->getPages());
            return ['text' => $text, 'pages' => $pages];
        } catch (\Throwable) {
            return ['text' => '', 'pages' => 0];
        }
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
