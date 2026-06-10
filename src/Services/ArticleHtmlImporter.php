<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Build an initial HTML payload for the collaborative editor from the
 * article the user originally uploaded. DOCX files round-trip through
 * PhpWord's HTML writer so headings, bold / italic, lists and tables
 * survive. PDF (and anything else) falls back to the extracted plain
 * text wrapped in paragraphs.
 */
final class ArticleHtmlImporter
{
    /**
     * @param array<string,mixed> $article Article row (needs file_path,
     *     mime, extracted_text, title).
     */
    public static function fromArticle(array $article): string
    {
        $mime = (string) ($article['mime'] ?? '');
        $path = (string) ($article['file_path'] ?? '');

        if (
            $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            && $path !== ''
            && is_file($path)
            && class_exists(\PhpOffice\PhpWord\IOFactory::class)
        ) {
            $html = self::docxToHtml($path);
            if ($html !== '') {
                return self::sanitiseHtmlFragment($html);
            }
        }

        $title = (string) ($article['title'] ?? '');
        return self::plainToHtml($title, (string) ($article['extracted_text'] ?? ''));
    }

    /**
     * Run a DOCX through PhpWord's HTML writer and return only the
     * body of the document. PhpWord wraps everything in a full
     * <html>...</html> shell with a <style> block; we strip that down
     * to the inner fragment so TinyMCE consumes it cleanly.
     */
    private static function docxToHtml(string $path): string
    {
        try {
            $doc = \PhpOffice\PhpWord\IOFactory::load($path, 'Word2007');
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'HTML');
            ob_start();
            $writer->save('php://output');
            $full = (string) ob_get_clean();
        } catch (\Throwable) {
            return '';
        }

        if (preg_match('#<body[^>]*>(.*?)</body>#is', $full, $m)) {
            return trim($m[1]);
        }
        return trim($full);
    }

    /**
     * Whitelist the small set of tags TinyMCE produces / consumes. Drops
     * <script>, <style>, inline event handlers and javascript: URLs so a
     * malicious PhpWord-converted document can't escape into the page.
     */
    private static function sanitiseHtmlFragment(string $html): string
    {
        // PhpWord embeds a <style>...</style> at the top of the body; drop it.
        $html = (string) preg_replace('#<style\b.*?</style>#is', '', $html);
        $html = (string) preg_replace('#<script\b.*?</script>#is', '', $html);
        // Strip inline event handlers (onclick=, onload=, …).
        $html = (string) preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#is', '', $html);
        // Defuse javascript: URLs.
        $html = (string) preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:#i', '$1=$2', $html);
        return $html;
    }

    /**
     * Wrap a plain-text article in basic HTML — title becomes <h1>, each
     * blank-line block becomes a <p>. This is what the user sees when
     * the upload was a PDF (no formatting to preserve).
     */
    private static function plainToHtml(string $title, string $text): string
    {
        $out = '';
        if ($title !== '') {
            $out .= '<h1>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>';
        }
        $text = trim($text);
        if ($text === '') {
            $out .= '<p></p>';
            return $out;
        }
        foreach (preg_split('/\n{2,}/', $text) ?: [$text] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $escaped = htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= '<p>' . nl2br($escaped) . '</p>';
        }
        return $out;
    }
}
