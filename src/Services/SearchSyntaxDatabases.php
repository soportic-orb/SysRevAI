<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Catalogue of the bibliographic databases SysRevAI knows how to record
 * a search-strategy syntax for. The keys are stored verbatim on
 * review_search_syntaxes.database_key and shown in the "+" picker on
 * the "Sintaxis de recerca" page.
 *
 * `label` is the public name; `syntax_hint` is a short note about the
 * usual query language (boolean keywords, field-tag conventions) so
 * the AI extractor can grade the candidate it finds in the protocol.
 */
final class SearchSyntaxDatabases
{
    /** @return array<int,array{key:string,label:string,syntax_hint:string}> */
    public static function all(): array
    {
        return [
            ['key' => 'pubmed',   'label' => 'PubMed (MEDLINE)',     'syntax_hint' => 'MeSH terms + free-text with field tags like [MeSH] / [tiab].'],
            ['key' => 'cinahl',   'label' => 'CINAHL',               'syntax_hint' => 'CINAHL Subject Headings (MH) + free-text with TI / AB / TX tags.'],
            ['key' => 'cochrane', 'label' => 'Cochrane Library',     'syntax_hint' => 'CENTRAL / CDSR — MeSH ascending/explode + ti,ab,kw search lines.'],
            ['key' => 'scopus',   'label' => 'Scopus',               'syntax_hint' => 'TITLE-ABS-KEY( ) with Boolean operators and proximity W/n.'],
            ['key' => 'wos',      'label' => 'Web of Science',       'syntax_hint' => 'TS=( ) (topic) + TI=( ) / AB=( ) with NEAR/n proximity.'],
            ['key' => 'eric',     'label' => 'ERIC',                 'syntax_hint' => 'Thesaurus descriptors ("DE") + Boolean combined with free-text.'],
            ['key' => 'ieee',     'label' => 'IEEE Xplore',          'syntax_hint' => 'Command Search — ("All Metadata":term) AND / OR / NEAR.'],
            ['key' => 'acm',      'label' => 'ACM Digital Library',  'syntax_hint' => 'Acmdlsearch.cfm Boolean — All Fields: + Title: + Abstract:.'],
            ['key' => 'psycinfo', 'label' => 'APA PsycINFO',         'syntax_hint' => 'APA Thesaurus (DE) + free-text with TI / AB / IDE field tags.'],
        ];
    }

    /** @return array<string,string> Key → public label. */
    public static function labels(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            $out[$row['key']] = $row['label'];
        }
        return $out;
    }

    /** @return string[] Allowed keys for whitelisting input. */
    public static function keys(): array
    {
        return array_map(static fn (array $r): string => $r['key'], self::all());
    }
}
