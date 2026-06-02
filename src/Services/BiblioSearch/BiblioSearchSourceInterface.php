<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * One external bibliographic database that knows how to translate a
 * free-text / DOI / PMID query into a list of normalised reference rows.
 *
 * Each adapter is responsible for:
 *   - taking the raw user query (already trimmed by the aggregator);
 *   - issuing the HTTP request(s) needed to satisfy it;
 *   - returning rows shaped like the ImportService output, so the same
 *     persistence layer (Reference::create) can ingest them without any
 *     adapter-specific glue.
 */
interface BiblioSearchSourceInterface
{
    /** Short stable identifier used in UI badges and audit logs. */
    public function name(): string;

    /**
     * Run the search. Implementations MUST return reference rows shaped
     * like the rest of SysRevAI's import pipeline:
     *
     *   ['title' => string, 'authors' => array<int,string>,
     *    'year' => int|null, 'journal' => string,
     *    'abstract' => string, 'doi' => string, 'pmid' => string,
     *    'url' => string, 'keywords' => array<int,string>]
     *
     * Sources MUST NOT throw on transport / parsing errors — they return
     * an empty array and the aggregator surfaces the failure to the user.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $query, int $limit = 20): array;
}
