<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Reference import parsers: RIS, BibTeX, CSV, PubMed XML and EndNote XML.
 *
 * Each parser returns a list of normalized reference arrays with the keys:
 *   title, authors[], year|null, journal, abstract, doi, pmid, url, keywords[]
 */
final class ImportService
{
    public const FORMATS = ['ris', 'bibtex', 'csv', 'pubmed', 'pubmed_text', 'endnote', 'freetext'];

    /** Guess the format from filename + content; null if unknown. */
    public static function detectFormat(string $filename, string $content): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $head = ltrim(substr($content, 0, 4096));

        if (in_array($ext, ['ris', 'nbib'], true) || preg_match('/^TY\s{2}-\s/m', $head)) {
            return 'ris';
        }
        if ($ext === 'bib' || str_contains($head, '@article') || preg_match('/^@\w+\s*\{/m', $head)) {
            return 'bibtex';
        }
        if (str_contains($head, '<PubmedArticle') || str_contains($head, '<PubmedArticleSet')) {
            return 'pubmed';
        }
        if (str_contains($head, '<records>') || str_contains($head, '<xml>') || str_contains($head, 'EndNote')) {
            return 'endnote';
        }
        // PubMed "Abstract (text)" export. Distinct from CSV by its
        // characteristic trailing PMID: line and absence of comma-delimited
        // headers — the same regex works on the .txt the user downloaded
        // from PubMed via "Save → All results → Format: Abstract → File".
        if (preg_match('/^PMID:\s*\d+/m', $content) && preg_match('/^\d+[\.\:]\s/m', $content)) {
            return 'pubmed_text';
        }
        if ($ext === 'csv' || str_contains($head, ',')) {
            return 'csv';
        }
        if ($ext === 'xml') {
            return str_contains($content, 'PubmedArticle') ? 'pubmed' : 'endnote';
        }
        if ($ext === 'txt' && str_contains($content, 'PMID')) {
            return 'pubmed_text';
        }
        return null;
    }

    /** @return array{refs:array<int,array>,errors:string[]} */
    public static function parse(string $content, string $format): array
    {
        return match ($format) {
            'ris'         => self::parseRis($content),
            'bibtex'      => self::parseBibtex($content),
            'csv'         => self::parseCsv($content),
            'pubmed'      => self::parsePubmed($content),
            'pubmed_text' => self::parsePubmedText($content),
            'endnote'     => self::parseEndnote($content),
            default       => ['refs' => [], 'errors' => ['Unknown format']],
        };
    }

    private static function blank(): array
    {
        return ['title' => '', 'authors' => [], 'year' => null, 'journal' => '',
                'abstract' => '', 'doi' => '', 'pmid' => '', 'url' => '', 'keywords' => []];
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
    }

    private static function year(string $s): ?int
    {
        return preg_match('/\b(1[5-9]\d{2}|20\d{2}|21\d{2})\b/', $s, $m) ? (int) $m[1] : null;
    }

    /* ── RIS / NBIB ────────────────────────────────────────────────────── */

    private static function parseRis(string $content): array
    {
        $refs = [];
        $errors = [];
        $cur = self::blank();
        $open = false;
        $lastTag = null;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9])\s{2}-\s?(.*)$/', $line, $m)) {
                $tag = $m[1];
                $val = trim($m[2]);
                $lastTag = $tag;
                $open = true;
                switch ($tag) {
                    case 'TI': case 'T1': $cur['title'] = $cur['title'] ?: $val; break;
                    case 'AU': case 'A1': if ($val !== '') $cur['authors'][] = $val; break;
                    case 'PY': case 'Y1': $cur['year'] = self::year($val); break;
                    case 'JO': case 'JF': case 'JA': case 'T2': $cur['journal'] = $cur['journal'] ?: $val; break;
                    case 'AB': case 'N2': $cur['abstract'] = trim($cur['abstract'] . ' ' . $val); break;
                    case 'DO': $cur['doi'] = $val; break;
                    case 'UR': case 'L1': $cur['url'] = $cur['url'] ?: $val; break;
                    case 'KW': if ($val !== '') $cur['keywords'][] = $val; break;
                    case 'AN': case 'ID': if ($cur['pmid'] === '' && preg_match('/(\d{6,9})/', $val, $mm)) $cur['pmid'] = $mm[1]; break;
                    case 'ER':
                        if ($cur['title'] !== '' || $cur['authors'] !== []) $refs[] = $cur;
                        $cur = self::blank();
                        $open = false;
                        break;
                }
            } elseif ($open && $lastTag !== null && trim($line) !== '') {
                // continuation line
                if ($lastTag === 'AB' || $lastTag === 'N2') {
                    $cur['abstract'] = trim($cur['abstract'] . ' ' . trim($line));
                }
            }
        }
        if ($cur['title'] !== '' || $cur['authors'] !== []) {
            $refs[] = $cur;
        }
        if ($refs === []) {
            $errors[] = 'No RIS records found.';
        }
        return ['refs' => array_map([self::class, 'normalize'], $refs), 'errors' => $errors];
    }

    /* ── BibTeX ─────────────────────────────────────────────────────────── */

    private static function parseBibtex(string $content): array
    {
        $refs = [];
        $errors = [];
        $len = strlen($content);
        $i = 0;
        while (($at = strpos($content, '@', $i)) !== false) {
            $brace = strpos($content, '{', $at);
            if ($brace === false) {
                break;
            }
            // Find matching closing brace for the entry.
            $depth = 0;
            $end = $brace;
            for ($j = $brace; $j < $len; $j++) {
                if ($content[$j] === '{') $depth++;
                elseif ($content[$j] === '}') { $depth--; if ($depth === 0) { $end = $j; break; } }
            }
            $body = substr($content, $brace + 1, $end - $brace - 1);
            $i = $end + 1;

            $ref = self::blank();
            foreach (self::bibtexFields($body) as $key => $val) {
                $val = self::clean(str_replace(['{', '}'], '', $val));
                switch ($key) {
                    case 'title': $ref['title'] = $val; break;
                    case 'author': $ref['authors'] = array_map('trim', preg_split('/\s+and\s+/i', $val) ?: []); break;
                    case 'year': $ref['year'] = self::year($val); break;
                    case 'journal': case 'journaltitle': case 'booktitle': $ref['journal'] = $ref['journal'] ?: $val; break;
                    case 'abstract': $ref['abstract'] = $val; break;
                    case 'doi': $ref['doi'] = $val; break;
                    case 'url': $ref['url'] = $ref['url'] ?: $val; break;
                    case 'pmid': $ref['pmid'] = $val; break;
                    case 'keywords': case 'keyword': $ref['keywords'] = array_map('trim', preg_split('/[;,]/', $val) ?: []); break;
                }
            }
            if ($ref['title'] !== '' || $ref['authors'] !== []) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            $errors[] = 'No BibTeX entries found.';
        }
        return ['refs' => array_map([self::class, 'normalize'], $refs), 'errors' => $errors];
    }

    /** Extract field => raw-value pairs from a BibTeX entry body. */
    private static function bibtexFields(string $body): array
    {
        $fields = [];
        $len = strlen($body);
        $i = 0;
        while ($i < $len) {
            if (!preg_match('/\G[\s,]*([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*/A', $body, $m, 0, $i)) {
                $i++;
                continue;
            }
            $key = strtolower($m[1]);
            $i += strlen($m[0]);
            if ($i >= $len) break;
            $val = '';
            if ($body[$i] === '{') {
                $depth = 0;
                for ($j = $i; $j < $len; $j++) {
                    if ($body[$j] === '{') $depth++;
                    elseif ($body[$j] === '}') { $depth--; if ($depth === 0) { $val = substr($body, $i + 1, $j - $i - 1); $i = $j + 1; break; } }
                }
            } elseif ($body[$i] === '"') {
                $j = strpos($body, '"', $i + 1);
                $j = $j === false ? $len : $j;
                $val = substr($body, $i + 1, $j - $i - 1);
                $i = $j + 1;
            } else {
                preg_match('/\G([^,]*)/A', $body, $mm, 0, $i);
                $val = trim($mm[1] ?? '');
                $i += strlen($mm[1] ?? '');
            }
            $fields[$key] = $val;
        }
        return $fields;
    }

    /* ── CSV ─────────────────────────────────────────────────────────────── */

    private static function parseCsv(string $content): array
    {
        $refs = [];
        $errors = [];
        $rows = array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            preg_split('/\r\n|\r|\n/', trim($content)) ?: []
        );
        if (count($rows) < 2) {
            return ['refs' => [], 'errors' => ['CSV has no data rows.']];
        }
        $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $find = static function (array $names) use ($headers): ?int {
            foreach ($headers as $idx => $h) {
                foreach ($names as $n) {
                    if ($h === $n || str_contains($h, $n)) return $idx;
                }
            }
            return null;
        };
        $cols = [
            'title'    => $find(['title']),
            'authors'  => $find(['authors', 'author']),
            'year'     => $find(['year', 'publication year']),
            'journal'  => $find(['journal', 'source', 'publication']),
            'abstract' => $find(['abstract']),
            'doi'      => $find(['doi']),
            'pmid'     => $find(['pmid', 'pubmed']),
            'url'      => $find(['url', 'link']),
            'keywords' => $find(['keywords', 'keyword']),
        ];
        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            $get = static fn (?int $c) => $c !== null && isset($row[$c]) ? trim((string) $row[$c]) : '';
            $ref = self::blank();
            $ref['title']    = $get($cols['title']);
            $ref['authors']  = $get($cols['authors']) !== '' ? array_map('trim', preg_split('/;|\band\b|\/\//', $get($cols['authors'])) ?: []) : [];
            $ref['year']     = self::year($get($cols['year']));
            $ref['journal']  = $get($cols['journal']);
            $ref['abstract'] = $get($cols['abstract']);
            $ref['doi']      = $get($cols['doi']);
            $ref['pmid']     = $get($cols['pmid']);
            $ref['url']      = $get($cols['url']);
            $ref['keywords'] = $get($cols['keywords']) !== '' ? array_map('trim', preg_split('/[;,]/', $get($cols['keywords'])) ?: []) : [];
            if ($ref['title'] !== '' || $ref['authors'] !== []) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            $errors[] = 'No usable rows in CSV (need a "title" column).';
        }
        return ['refs' => array_map([self::class, 'normalize'], $refs), 'errors' => $errors];
    }

    /* ── PubMed XML ──────────────────────────────────────────────────────── */

    private static function parsePubmed(string $content): array
    {
        $doc = self::loadXml($content);
        if ($doc === null) {
            return ['refs' => [], 'errors' => ['Invalid XML.']];
        }
        $xp = new \DOMXPath($doc);
        $refs = [];
        foreach ($xp->query('//PubmedArticle') as $node) {
            $ref = self::blank();
            $ref['title']   = self::clean(self::xpText($xp, './/ArticleTitle', $node));
            $ref['journal'] = self::clean(self::xpText($xp, './/Journal/Title', $node));
            $ref['year']    = self::year(self::xpText($xp, './/JournalIssue/PubDate/Year', $node)
                              ?: self::xpText($xp, './/JournalIssue/PubDate/MedlineDate', $node));
            $abs = [];
            foreach ($xp->query('.//Abstract/AbstractText', $node) as $a) {
                $abs[] = $a->textContent;
            }
            $ref['abstract'] = self::clean(implode(' ', $abs));
            $ref['pmid'] = self::clean(self::xpText($xp, './/PMID', $node));
            foreach ($xp->query('.//ArticleId[@IdType="doi"] | .//ELocationID[@EIdType="doi"]', $node) as $d) {
                $ref['doi'] = self::clean($d->textContent);
                break;
            }
            foreach ($xp->query('.//AuthorList/Author', $node) as $au) {
                $last = self::xpText($xp, './LastName', $au);
                $fore = self::xpText($xp, './ForeName', $au) ?: self::xpText($xp, './Initials', $au);
                $name = trim($last . ($fore ? ', ' . $fore : ''));
                if ($name !== '') $ref['authors'][] = $name;
            }
            foreach ($xp->query('.//Keyword', $node) as $k) {
                $kw = self::clean($k->textContent);
                if ($kw !== '') $ref['keywords'][] = $kw;
            }
            if ($ref['title'] !== '') $refs[] = $ref;
        }
        return ['refs' => array_map([self::class, 'normalize'], $refs),
                'errors' => $refs === [] ? ['No PubMed articles found.'] : []];
    }

    /* ── PubMed Abstract (text) ──────────────────────────────────────────
     *
     * Multi-paragraph text export produced by PubMed when the user
     * picks "Save → All results → Format: Abstract → File". One record
     * looks roughly like:
     *
     *   1: J Eval Clin Pract. 2023 Aug;29(5):793-803. doi: 10.1111/jep.13838.
     *
     *   Title of the article on one or more lines.
     *
     *   Smith J(1), Jones K(2), Brown L(1).
     *
     *   Author information:
     *   (1)Department X, University Y.
     *
     *   Abstract / Background / Methods / Results / Conclusions paragraphs.
     *
     *   DOI: 10.1111/jep.13838
     *   PMCID: PMC1234567
     *   PMID: 36868880 [Indexed for MEDLINE]
     *
     * Records are separated by a blank line followed by "<n>:" or
     * "<n>.". The parser is deliberately tolerant — the field order
     * shifts slightly between PubMed versions and account languages, so
     * we extract DOI / PMID by line-anchored regex first, then peel the
     * remaining paragraphs into journal-header, title, authors, abstract.
     */
    private static function parsePubmedText(string $content): array
    {
        $content = (string) preg_replace("/\r\n|\r/", "\n", $content);

        // Split records on either "<blank line><digits><.|:><space>" (the
        // record header) or "PMID: …" lines so an export without leading
        // numbers (rare but possible) still chunks correctly.
        $chunks = preg_split('/\n\s*\n(?=\d+[.:]\s)/', $content) ?: [];
        if (count($chunks) <= 1) {
            // Fallback: split on blank line right after a PMID: line.
            $chunks = preg_split('/^PMID:\s*\d+.*$\n\s*\n/m', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $refs = [];
        $errors = [];
        foreach ($chunks as $chunk) {
            $ref = self::parsePubmedTextRecord(trim($chunk));
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            $errors[] = 'No PubMed text records found.';
        }
        return [
            'refs'   => array_map([self::class, 'normalize'], $refs),
            'errors' => $errors,
        ];
    }

    private static function parsePubmedTextRecord(string $chunk): ?array
    {
        if ($chunk === '') {
            return null;
        }
        $ref = self::blank();

        // PMID — last line of every record in this format.
        if (preg_match('/^PMID:\s*(\d+)/mi', $chunk, $m)) {
            $ref['pmid'] = $m[1];
        }
        // DOI — either an explicit "DOI: …" line near the bottom, or
        // inline in the journal header ("…; doi: 10.x/y. Epub…").
        if (preg_match('/^DOI:\s*(\S+)/mi', $chunk, $m)) {
            $ref['doi'] = rtrim($m[1], '.');
        } elseif (preg_match('/\bdoi:\s*(10\.\S+?)(?=[\s.]|$)/i', $chunk, $m)) {
            $ref['doi'] = rtrim($m[1], '.');
        }
        // Keywords — explicit "Keywords:" line.
        if (preg_match('/^Keywords?:\s*(.+?)$/mi', $chunk, $m)) {
            $ref['keywords'] = array_values(array_filter(
                array_map('trim', preg_split('/[;,]/', $m[1]) ?: []),
                static fn (string $s): bool => $s !== ''
            ));
        }

        // Drop the leading record marker ("1: " or "1. ") if any.
        $chunk = (string) preg_replace('/^\s*\d+[.:]\s+/', '', $chunk);

        // Break into paragraphs and prune the boilerplate trailers we
        // already pulled the identifiers from, so what's left is article
        // metadata.
        $paragraphs = preg_split('/\n\s*\n/', $chunk) ?: [];
        $paragraphs = array_map(
            static fn (string $p): string => trim((string) preg_replace('/\s+/u', ' ', $p)),
            $paragraphs
        );
        $paragraphs = array_values(array_filter($paragraphs, static fn (string $p): bool => $p !== ''));

        // 1. Journal header — first paragraph, usually contains "<Journal>. <Year> ..."
        if ($paragraphs !== []) {
            $header = (string) array_shift($paragraphs);
            if (preg_match('/^(.+?)\.\s+(\d{4})/u', $header, $m)) {
                $ref['journal'] = trim($m[1]);
                $ref['year']    = (int) $m[2];
            } elseif (preg_match('/^(.+?)\./u', $header, $m)) {
                $ref['journal'] = trim($m[1]);
            }
        }

        // 2. Title — first non-trailer paragraph that doesn't look like
        //    an authors block (single-line, ends in ".", mostly capitalised
        //    surnames). The check is intentionally loose: title text
        //    contains spaces too so we just take the first paragraph that
        //    isn't a known trailer.
        while ($paragraphs !== []) {
            $p = $paragraphs[0];
            if (self::isPubmedTrailer($p)) {
                array_shift($paragraphs);
                continue;
            }
            $ref['title'] = (string) array_shift($paragraphs);
            break;
        }

        // 3. Authors — next paragraph if it parses as a comma-separated
        //    list of names (with optional affiliation markers "(1)").
        if ($paragraphs !== [] && self::looksLikeAuthorList($paragraphs[0])) {
            $authorsStr = (string) array_shift($paragraphs);
            // Strip affiliation markers like "(1)" / "(1,2)".
            $authorsStr = (string) preg_replace('/\([\d,\s]+\)/', '', $authorsStr);
            $authorsStr = trim(rtrim($authorsStr, '.'));
            $authors = preg_split('/,\s*(?=\S)/', $authorsStr) ?: [];
            $authors = array_map('trim', $authors);
            $authors = array_values(array_filter($authors, static fn (string $a): bool => $a !== ''));
            $ref['authors'] = $authors;
        }

        // 4. Abstract — the rest, minus known trailers (affiliations,
        //    copyright, comments, identifier blocks, etc.).
        $abstract = [];
        foreach ($paragraphs as $p) {
            if (self::isPubmedTrailer($p)) {
                continue;
            }
            $abstract[] = $p;
        }
        if ($abstract !== []) {
            $ref['abstract'] = implode("\n\n", $abstract);
        }

        // Reject empty rows — needs at least one identifying field so
        // the dedup key has something to grip.
        if ($ref['title'] === '' && $ref['pmid'] === '' && $ref['doi'] === '') {
            return null;
        }
        return $ref;
    }

    /** Paragraph patterns we strip before treating the rest as abstract. */
    private static function isPubmedTrailer(string $p): bool
    {
        return (bool) preg_match(
            '/^(Author information|Author Contributions|Comment in|Comment on|Erratum in|Erratum for|Update of|Update in|Conflict of interest|Funding|Copyright|©|DOI:|PMCID?:|PMID:|MeSH|Substances|EID|PII|\[)/i',
            $p
        );
    }

    /**
     * A paragraph "looks like" an author list when it's a single-line,
     * comma-separated string of capitalised names — optionally annotated
     * with affiliation markers — that ends in a period.
     */
    private static function looksLikeAuthorList(string $p): bool
    {
        if (mb_strlen($p) > 800) {
            return false;
        }
        if (!str_ends_with(rtrim($p), '.')) {
            return false;
        }
        if (!str_contains($p, ',')) {
            // A single-author record still passes if it starts with a capital
            // and has a short token count (Lastname Initials.).
            return (bool) preg_match('/^\p{Lu}[\p{L}\'-]+\s+\p{Lu}/u', $p) && mb_strlen($p) < 80;
        }
        // Authors are short comma-separated tokens; an abstract usually
        // has long sentences. Reject if any single token between commas
        // is longer than ~60 chars (likely prose).
        $tokens = array_map('trim', explode(',', $p));
        foreach ($tokens as $t) {
            if (mb_strlen($t) > 60) {
                return false;
            }
        }
        return (bool) preg_match('/^\p{Lu}/u', $p);
    }

    /* ── EndNote XML ─────────────────────────────────────────────────────── */

    private static function parseEndnote(string $content): array
    {
        $doc = self::loadXml($content);
        if ($doc === null) {
            return ['refs' => [], 'errors' => ['Invalid XML.']];
        }
        $xp = new \DOMXPath($doc);
        $refs = [];
        foreach ($xp->query('//records/record') as $node) {
            $ref = self::blank();
            $ref['title']    = self::clean(self::xpText($xp, './/titles/title', $node));
            $ref['journal']  = self::clean(self::xpText($xp, './/periodical/full-title', $node) ?: self::xpText($xp, './/titles/secondary-title', $node));
            $ref['year']     = self::year(self::xpText($xp, './/dates/year', $node));
            $ref['abstract'] = self::clean(self::xpText($xp, './/abstract', $node));
            $ref['doi']      = self::clean(self::xpText($xp, './/electronic-resource-num', $node));
            $ref['url']      = self::clean(self::xpText($xp, './/urls//url', $node));
            foreach ($xp->query('.//contributors/authors/author', $node) as $au) {
                $name = self::clean($au->textContent);
                if ($name !== '') $ref['authors'][] = $name;
            }
            foreach ($xp->query('.//keywords/keyword', $node) as $k) {
                $kw = self::clean($k->textContent);
                if ($kw !== '') $ref['keywords'][] = $kw;
            }
            if ($ref['title'] !== '') $refs[] = $ref;
        }
        return ['refs' => array_map([self::class, 'normalize'], $refs),
                'errors' => $refs === [] ? ['No EndNote records found.'] : []];
    }

    private static function loadXml(string $content): ?\DOMDocument
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($content, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? $doc : null;
    }

    private static function xpText(\DOMXPath $xp, string $query, \DOMNode $ctx): string
    {
        $n = $xp->query($query, $ctx);
        return ($n !== false && $n->length > 0) ? trim($n->item(0)->textContent) : '';
    }

    /** Final tidy-up applied to every parsed reference. */
    private static function normalize(array $ref): array
    {
        $ref['title']    = self::clean((string) $ref['title']);
        $ref['journal']  = self::clean((string) $ref['journal']);
        $ref['abstract'] = trim((string) $ref['abstract']);
        $ref['doi']      = strtolower(trim(preg_replace('#^https?://(dx\.)?doi\.org/#i', '', (string) $ref['doi']) ?? ''));
        $ref['pmid']     = preg_replace('/\D/', '', (string) $ref['pmid']) ?? '';
        $ref['authors']  = array_values(array_filter(array_map('trim', $ref['authors'])));
        $ref['keywords'] = array_values(array_filter(array_map('trim', $ref['keywords'])));
        return $ref;
    }
}
