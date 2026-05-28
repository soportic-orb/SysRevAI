<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * arXiv — preprint PDFs. Detected when the DOI uses the arXiv prefix
 * (10.48550/arXiv.<id>) or the journal field mentions arXiv.
 *
 * Endpoint: GET http://export.arxiv.org/api/query?id_list={arxiv_id}
 * PDF:      https://arxiv.org/pdf/{arxiv_id}.pdf
 * Rate limit: 1 req / 3 s (strict).
 */
final class ArxivSource extends BaseHttpSource
{
    private const QUERY_BASE = 'http://export.arxiv.org/api/query';
    private const PDF_BASE   = 'https://arxiv.org/pdf/';

    public function name(): string
    {
        return 'arxiv';
    }

    public function supports(array $reference): bool
    {
        return $this->extractArxivId($reference) !== null;
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $id = $this->extractArxivId($reference);
        if ($id === null) {
            return null;
        }

        $res = $this->get(
            self::QUERY_BASE . '?id_list=' . rawurlencode($id),
            ['Accept: application/atom+xml']
        );
        if ($res['error'] !== null || $res['status'] !== 200 || $res['body'] === '') {
            return null;
        }

        // Sanity check: the Atom feed should at least mention the requested id.
        if (!str_contains($res['body'], $id)) {
            return null;
        }

        return new FullTextResult(
            source: $this->name(),
            pdfUrl: self::PDF_BASE . $id . '.pdf',
            licenseType: 'arxiv-perpetual',
            version: 'preprint',
            metadata: ['arxiv_id' => $id],
            confidence: 0.9,
        );
    }

    public function verifyConnection(): array
    {
        $res = $this->get(self::QUERY_BASE . '?search_query=all:exercise&max_results=1', ['Accept: application/atom+xml']);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'arXiv OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 1, 'per_seconds' => 3];
    }

    /** Pull an arXiv id from the DOI ("10.48550/arXiv.2401.12345") or the URL. */
    private function extractArxivId(array $reference): ?string
    {
        $doi = strtolower((string) ($reference['doi'] ?? ''));
        if (preg_match('#10\.48550/arxiv\.([0-9a-z.\-/]+)#', $doi, $m)) {
            return $m[1];
        }
        $url = (string) ($reference['url'] ?? '');
        if (preg_match('#arxiv\.org/(?:abs|pdf)/([0-9a-z.\-/]+)#i', $url, $m)) {
            return preg_replace('/\.pdf$/i', '', $m[1]);
        }
        $journal = strtolower((string) ($reference['journal'] ?? ''));
        if (str_contains($journal, 'arxiv')
            && !empty($reference['url'])
            && preg_match('#([0-9]{4}\.[0-9]{4,5}(?:v[0-9]+)?)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
