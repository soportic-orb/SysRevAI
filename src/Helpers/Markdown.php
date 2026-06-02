<?php

declare(strict_types=1);

namespace SysRevAI\Helpers;

/**
 * Tiny, dependency-free Markdown renderer for AI chat replies.
 *
 * Supports the subset Claude tends to emit: bold, italic, inline code,
 * fenced and indented code blocks, headings (#..####), unordered / ordered
 * lists, blockquotes, links, simple pipe tables and paragraphs.
 *
 * Safety: every input character is HTML-escaped first, then a small set
 * of allow-listed patterns inject tags. We never run user input through a
 * raw HTML pass, so there is no XSS surface even when the model
 * hallucinates `<script>` blobs.
 */
final class Markdown
{
    public static function render(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $md = trim($md);
        if ($md === '') {
            return '';
        }

        // Pull fenced code blocks out first so their contents don't get
        // further markdown processing.
        $codeBlocks = [];
        $md = (string) preg_replace_callback(
            '/```([a-zA-Z0-9_+\-]*)\n(.*?)```/s',
            static function (array $m) use (&$codeBlocks): string {
                $lang = $m[1] !== '' ? ' class="lang-' . preg_replace('/[^a-zA-Z0-9_+\-]/', '', $m[1]) . '"' : '';
                $body = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $idx = count($codeBlocks);
                $codeBlocks[] = '<pre class="md-pre"><code' . $lang . '>' . $body . '</code></pre>';
                return "\x01CODEBLOCK{$idx}\x01";
            },
            $md
        );

        // Pipe tables. Detected as a header row, a separator row of dashes,
        // and one or more body rows.
        $tables = [];
        $md = (string) preg_replace_callback(
            '/(?:^|\n)((?:\|[^\n]+\|\n)\|[\s:|\-]+\|\n(?:\|[^\n]+\|\n?)+)/',
            static function (array $m) use (&$tables): string {
                $idx = count($tables);
                $tables[] = self::renderTable($m[1]);
                return "\n\x01TABLE{$idx}\x01\n";
            },
            $md
        );

        // Split into blocks (separated by blank lines) so paragraphs,
        // lists and headings don't bleed into each other.
        $blocks = preg_split('/\n{2,}/', $md) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $block = rtrim($block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^\x01(?:CODEBLOCK|TABLE)\d+\x01$/', trim($block))) {
                $out[] = trim($block);
                continue;
            }
            $out[] = self::renderBlock($block);
        }

        $html = implode("\n", $out);

        // Restore placeholders.
        $html = (string) preg_replace_callback(
            '/\x01CODEBLOCK(\d+)\x01/',
            static fn (array $m): string => $codeBlocks[(int) $m[1]] ?? '',
            $html
        );
        $html = (string) preg_replace_callback(
            '/\x01TABLE(\d+)\x01/',
            static fn (array $m): string => $tables[(int) $m[1]] ?? '',
            $html
        );

        return $html;
    }

    private static function renderBlock(string $block): string
    {
        $lines = explode("\n", $block);
        $first = $lines[0];

        // Heading (#..####).
        if (preg_match('/^(#{1,4})\s+(.+)$/', $first, $m) && count($lines) === 1) {
            $level = min(4, strlen($m[1])) + 2; // h3..h6 — chat lives inside a card with its own h3
            $tag = 'h' . $level;
            return "<{$tag} class=\"md-h\">" . self::inline($m[2]) . "</{$tag}>";
        }

        // Blockquote.
        if (preg_match('/^>\s?/', $first)) {
            $stripped = [];
            foreach ($lines as $ln) {
                $stripped[] = preg_replace('/^>\s?/', '', $ln);
            }
            return '<blockquote class="md-quote">' . self::renderBlock(implode("\n", $stripped)) . '</blockquote>';
        }

        // Unordered list (- or *).
        if (preg_match('/^(\s*)[*\-]\s+/', $first)) {
            return self::renderList($lines, false);
        }
        // Ordered list (1.).
        if (preg_match('/^\s*\d+\.\s+/', $first)) {
            return self::renderList($lines, true);
        }

        // Paragraph: turn single newlines into <br>.
        $escaped = [];
        foreach ($lines as $ln) {
            $escaped[] = self::inline($ln);
        }
        return '<p class="md-p">' . implode('<br>', $escaped) . '</p>';
    }

    /**
     * Render a list block. Items can span multiple lines (continuation
     * lines without a bullet marker are appended to the previous item).
     *
     * @param string[] $lines
     */
    private static function renderList(array $lines, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $cls = $ordered ? 'md-ol' : 'md-ul';
        $items = [];
        $cur = null;
        $pattern = $ordered ? '/^\s*\d+\.\s+(.*)$/' : '/^\s*[*\-]\s+(.*)$/';
        foreach ($lines as $ln) {
            if (preg_match($pattern, $ln, $m)) {
                if ($cur !== null) {
                    $items[] = $cur;
                }
                $cur = $m[1];
            } else {
                $cur = ($cur === null ? '' : $cur . ' ') . trim($ln);
            }
        }
        if ($cur !== null) {
            $items[] = $cur;
        }

        $rendered = '';
        foreach ($items as $it) {
            $rendered .= '<li>' . self::inline($it) . '</li>';
        }
        return "<{$tag} class=\"{$cls}\">{$rendered}</{$tag}>";
    }

    private static function renderTable(string $raw): string
    {
        $rows = array_values(array_filter(explode("\n", trim($raw)), static fn (string $s): bool => trim($s) !== ''));
        if (count($rows) < 2) {
            return '';
        }
        $header = self::splitRow($rows[0]);
        // rows[1] is the alignment separator — discarded.
        $body = array_slice($rows, 2);

        $html = '<table class="md-table"><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . self::inline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($body as $row) {
            $cells = self::splitRow($row);
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . self::inline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /** @return string[] */
    private static function splitRow(string $row): array
    {
        $row = trim($row);
        $row = preg_replace('/^\||\|$/', '', $row) ?? '';
        $parts = explode('|', $row);
        return array_map('trim', $parts);
    }

    /**
     * Inline formatting: HTML-escape first, then carve out tags from
     * patterns we trust. The escape pass means any literal `<` in the
     * model output becomes `&lt;` and can never re-enter the DOM as a tag.
     */
    private static function inline(string $text): string
    {
        $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Inline code first so its contents aren't bold/italicised.
        $codes = [];
        $s = (string) preg_replace_callback(
            '/`([^`\n]+)`/',
            static function (array $m) use (&$codes): string {
                $idx = count($codes);
                $codes[] = '<code class="md-code">' . $m[1] . '</code>';
                return "\x01IC{$idx}\x01";
            },
            $s
        );

        // Links: [text](http://…) — only http(s) and mailto, escape the href.
        $s = (string) preg_replace_callback(
            '/\[([^\]]+)\]\(((?:https?:|mailto:)[^)\s]+)\)/i',
            static function (array $m): string {
                $href = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            },
            $s
        );

        // Bold + italic. **bold**, *italic*, _italic_.
        $s = (string) preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $s);
        $s = (string) preg_replace('/(?<![\w_])_([^_\n]+)_(?![\w_])/', '<em>$1</em>', $s);

        // Restore inline code.
        $s = (string) preg_replace_callback(
            '/\x01IC(\d+)\x01/',
            static fn (array $m): string => $codes[(int) $m[1]] ?? '',
            $s
        );

        return $s;
    }
}
