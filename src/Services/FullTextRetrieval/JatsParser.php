<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Tolerant JATS XML reader (PMC / Europe PMC).
 *
 * Returns a structured payload (title, abstract, body sections) plus a single
 * plain-text rendition that the rest of the platform (Claude prompts, summaries,
 * full-text search) can consume just like the smalot/pdfparser output.
 */
final class JatsParser
{
    /**
     * @return array{
     *     title:string, abstract:string,
     *     sections:array<int,array{title:string,text:string}>,
     *     plain_text:string,
     *     section_count:int
     * }|null  null when the input is not valid XML.
     */
    public static function parse(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($xml, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }

        $xp = new \DOMXPath($doc);

        $title = self::xpText($xp, '//front//title-group/article-title');
        if ($title === '') {
            $title = self::xpText($xp, '//article-title');
        }

        $abstract = '';
        $abstractNodes = $xp->query('//front//abstract');
        if ($abstractNodes !== false) {
            foreach ($abstractNodes as $node) {
                $abstract .= self::collectText($node) . "\n\n";
            }
        }
        $abstract = trim($abstract);

        $sections = [];
        $bodySecs = $xp->query('//body/sec | //body//sec');
        if ($bodySecs !== false) {
            foreach ($bodySecs as $secNode) {
                $secTitle = self::xpText($xp, './title', $secNode);
                $secText  = self::collectText($secNode, ['title']);
                $secText  = trim(preg_replace('/\s+/u', ' ', $secText) ?? '');
                if ($secText === '' && $secTitle === '') {
                    continue;
                }
                $sections[] = ['title' => $secTitle, 'text' => $secText];
            }
        }
        // Fallback: when body has no <sec>, take the body's direct text.
        if ($sections === []) {
            $body = $xp->query('//body')->item(0);
            if ($body !== null) {
                $bodyText = trim((string) self::collectText($body));
                if ($bodyText !== '') {
                    $sections[] = ['title' => '', 'text' => $bodyText];
                }
            }
        }

        $plain = $title;
        if ($abstract !== '') {
            $plain .= "\n\nAbstract\n" . $abstract;
        }
        foreach ($sections as $s) {
            $plain .= "\n\n" . ($s['title'] !== '' ? $s['title'] . "\n" : '') . $s['text'];
        }
        $plain = self::normalize($plain);

        return [
            'title'         => self::normalize($title),
            'abstract'      => self::normalize($abstract),
            'sections'      => $sections,
            'plain_text'    => $plain,
            'section_count' => count($sections),
        ];
    }

    private static function xpText(\DOMXPath $xp, string $query, ?\DOMNode $ctx = null): string
    {
        $nodes = $ctx === null ? $xp->query($query) : $xp->query($query, $ctx);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        return trim((string) $nodes->item(0)->textContent);
    }

    /**
     * Recursively concatenate the text of a node, skipping inline children
     * named in $skip (e.g. nested <title> already captured separately).
     */
    private static function collectText(\DOMNode $node, array $skip = []): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, $skip, true)) {
                continue;
            }
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->textContent;
            } elseif ($child instanceof \DOMElement) {
                // Paragraph-like elements get newlines around them.
                $isBlock = in_array($child->localName, ['p', 'sec', 'list', 'list-item', 'caption', 'table-wrap', 'fig', 'title'], true);
                if ($isBlock) {
                    $out .= "\n" . self::collectText($child, $skip) . "\n";
                } else {
                    $out .= self::collectText($child, $skip);
                }
            }
        }
        return $out;
    }

    /** Collapse whitespace; preserve double-newlines as paragraph breaks. */
    private static function normalize(string $text): string
    {
        $text = (string) preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F]/u", '', $text);
        $text = (string) preg_replace("/[ \t]+/", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
