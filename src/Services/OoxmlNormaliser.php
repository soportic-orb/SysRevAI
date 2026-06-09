<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Fix the child-element order of <w:pPr> and <w:rPr> nodes inside a
 * Word2007 .docx payload so it satisfies the OOXML CT_PPrBase /
 * CT_RPrBase content models.
 *
 * The bundled PhpWord 1.x writer emits paragraph and run properties in
 * insertion order rather than the order ECMA-376 requires. Microsoft
 * Word tolerates that on simple paragraphs but rejects the document
 * ("Word detected an error trying to open the file") when several
 * out-of-order elements stack up — e.g. our shaded callouts combining
 * shd + pBdr + spacing + ind, or any rPr that mixes bold/italic with
 * colour and size.
 *
 * We can't reasonably patch PhpWord, so we patch the output: load
 * word/document.xml, walk every pPr/rPr, sort its direct children by
 * the canonical schema position and write it back into the zip.
 */
final class OoxmlNormaliser
{
    /**
     * Canonical child-element order for w:pPr (CT_PPrBase + CT_PPr extras).
     * Names without the w: prefix; lookup is local-name based.
     *
     * @var list<string>
     */
    private const PPR_ORDER = [
        'pStyle',
        'keepNext',
        'keepLines',
        'pageBreakBefore',
        'framePr',
        'widowControl',
        'numPr',
        'suppressLineNumbers',
        'pBdr',
        'shd',
        'tabs',
        'suppressAutoHyphens',
        'kinsoku',
        'wordWrap',
        'overflowPunct',
        'topLinePunct',
        'autoSpaceDE',
        'autoSpaceDN',
        'bidi',
        'adjustRightInd',
        'snapToGrid',
        'spacing',
        'ind',
        'contextualSpacing',
        'mirrorIndents',
        'suppressOverlap',
        'jc',
        'textDirection',
        'textAlignment',
        'textboxTightWrap',
        'outlineLvl',
        'divId',
        'cnfStyle',
        'rPr',     // run-of-paragraph-marker properties, always last
        'sectPr',
        'pPrChange',
    ];

    /**
     * Canonical child-element order for w:rPr (CT_RPrBase).
     *
     * @var list<string>
     */
    private const RPR_ORDER = [
        'rStyle',
        'rFonts',
        'b',
        'bCs',
        'i',
        'iCs',
        'caps',
        'smallCaps',
        'strike',
        'dstrike',
        'outline',
        'shadow',
        'emboss',
        'imprint',
        'noProof',
        'snapToGrid',
        'vanish',
        'webHidden',
        'color',
        'spacing',
        'w',
        'kern',
        'position',
        'sz',
        'szCs',
        'highlight',
        'u',
        'effect',
        'bdr',
        'shd',
        'fitText',
        'vertAlign',
        'rtl',
        'cs',
        'em',
        'lang',
        'eastAsianLayout',
        'specVanish',
        'oMath',
        'rPrChange',
    ];

    /**
     * Take the raw bytes of a .docx file, rewrite document.xml with
     * canonical pPr/rPr ordering and return the new bytes. Falls back
     * to the original payload if anything goes wrong so a normalisation
     * bug can never replace a "broken but somewhat openable" file with
     * nothing.
     */
    public static function normalise(string $docxBytes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docxfix_');
        if ($tmp === false) {
            return $docxBytes;
        }
        file_put_contents($tmp, $docxBytes);

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            return $docxBytes;
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (is_string($xml) && $xml !== '') {
                $fixed = self::reorderXml($xml);
                if ($fixed !== null && $fixed !== $xml) {
                    $zip->addFromString('word/document.xml', $fixed);
                }
            }
            $zip->close();
            $out = (string) @file_get_contents($tmp);
            return $out !== '' ? $out : $docxBytes;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Reorder every <w:pPr> / <w:rPr> direct children list in `$xml`
     * to canonical schema order. Returns null when the document fails
     * to parse so the caller can fall back to the original bytes.
     */
    private static function reorderXml(string $xml): ?string
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            return null;
        }

        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        self::reorderAll($doc, $ns, 'pPr', self::PPR_ORDER);
        self::reorderAll($doc, $ns, 'rPr', self::RPR_ORDER);

        return $doc->saveXML();
    }

    /**
     * @param list<string> $order
     */
    private static function reorderAll(\DOMDocument $doc, string $ns, string $localName, array $order): void
    {
        $positions = array_flip($order);
        $nodes = $doc->getElementsByTagNameNS($ns, $localName);
        // getElementsByTagNameNS returns a live NodeList; collect first.
        $list = [];
        foreach ($nodes as $node) {
            $list[] = $node;
        }
        foreach ($list as $parent) {
            self::reorderChildren($parent, $positions);
        }
    }

    /**
     * @param array<string,int> $positions  local-name → canonical index
     */
    private static function reorderChildren(\DOMElement $parent, array $positions): void
    {
        $children = [];
        $unknown = [];
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $name = $child->localName ?? $child->nodeName;
            if (isset($positions[$name])) {
                $children[] = ['idx' => $positions[$name], 'node' => $child];
            } else {
                // Unknown element — keep at the end in original order so
                // we don't accidentally drop anything we don't recognise.
                $unknown[] = $child;
            }
        }

        // Stable sort: preserve original order between elements that share
        // the same canonical index (none expected, but be safe).
        $i = 0;
        foreach ($children as &$entry) {
            $entry['seq'] = $i++;
        }
        unset($entry);
        usort($children, static function (array $a, array $b): int {
            return ($a['idx'] <=> $b['idx']) ?: ($a['seq'] <=> $b['seq']);
        });

        // Detach all then re-append in canonical order.
        foreach (iterator_to_array($parent->childNodes) as $child) {
            $parent->removeChild($child);
        }
        foreach ($children as $entry) {
            $parent->appendChild($entry['node']);
        }
        foreach ($unknown as $node) {
            $parent->appendChild($node);
        }
    }
}
