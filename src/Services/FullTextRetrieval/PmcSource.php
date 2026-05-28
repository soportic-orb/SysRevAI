<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

use SysRevAI\Core\Config;

/**
 * PMC (NCBI E-utilities) — DOI → PMCID → full text XML / PDF.
 *
 * ESearch: GET https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pmc&term={doi}[DOI]&retmode=json
 * EFetch:  GET https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&id={pmcid}&rettype=xml
 * PDF:     https://www.ncbi.nlm.nih.gov/pmc/articles/PMC{pmcid}/pdf/
 *
 * The API key (encrypted) is optional but lifts the rate limit from 3 to 10 req/s.
 */
final class PmcSource extends BaseHttpSource
{
    private const ESEARCH = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi';
    private const PDF_BASE = 'https://www.ncbi.nlm.nih.gov/pmc/articles/';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'pmc';
    }

    public function supports(array $reference): bool
    {
        return $this->normalizeDoi($reference['doi'] ?? null) !== null
            || !empty($reference['pmid']);
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $pmcid = $this->findPmcid($reference);
        if ($pmcid === null) {
            return null;
        }

        $pdfUrl = self::PDF_BASE . 'PMC' . $pmcid . '/pdf/';
        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: 'pmc-open-access',
            version: 'published',
            metadata: [
                'pmcid'   => 'PMC' . $pmcid,
                'xml_url' => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&id=' . $pmcid . '&rettype=xml' . $this->authSuffix(),
            ],
            confidence: 0.95,
        );
    }

    public function verifyConnection(): array
    {
        $url = self::ESEARCH . '?db=pmc&term=' . rawurlencode(self::VERIFY_DOI . '[DOI]') . '&retmode=json' . $this->authSuffix();
        $res = $this->get($url);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'PMC (NCBI) OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return $this->hasApiKey()
            ? ['requests' => 10, 'per_seconds' => 1]
            : ['requests' => 3, 'per_seconds' => 1];
    }

    private function findPmcid(array $reference): ?string
    {
        $doi  = $this->normalizeDoi($reference['doi'] ?? null);
        $pmid = preg_replace('/\D/', '', (string) ($reference['pmid'] ?? '')) ?? '';

        if ($doi !== null) {
            $term = $doi . '[DOI]';
        } elseif ($pmid !== '') {
            $term = $pmid . '[PMID]';
        } else {
            return null;
        }

        $url = self::ESEARCH . '?db=pmc&term=' . rawurlencode($term) . '&retmode=json' . $this->authSuffix();
        $data = $this->getJson($url);
        $idList = $data['esearchresult']['idlist'] ?? null;
        if (!is_array($idList) || $idList === []) {
            return null;
        }
        $id = (string) $idList[0];
        return preg_match('/^\d+$/', $id) ? $id : null;
    }

    private function hasApiKey(): bool
    {
        return Config::hasEncrypted('fulltext.pmc.api_key') || Config::hasEncrypted('pubmed.api_key');
    }

    private function authSuffix(): string
    {
        $parts = [];
        $email = $this->politeEmail();
        if ($email !== '') {
            $parts[] = 'email=' . rawurlencode($email);
        }
        $key = (string) (setting('fulltext.pmc.api_key') ?? setting('pubmed.api_key') ?? '');
        if ($key !== '') {
            $parts[] = 'api_key=' . rawurlencode($key);
        }
        $parts[] = 'tool=' . rawurlencode('SysRevAI');
        return $parts === [] ? '' : '&' . implode('&', $parts);
    }
}
