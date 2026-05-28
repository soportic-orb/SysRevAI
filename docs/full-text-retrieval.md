# Full-text retrieval module

SysRevAI's full-text retrieval module automatically tries to obtain the PDF
or structured XML of imported references by walking a configured chain of
**official academic APIs**. It only uses publicly documented endpoints and
never bypasses paywalls.

## What it does

1. For each reference, walk the priority chain of enabled sources.
2. Stop at the first source that returns a downloadable PDF URL or JATS XML
   (unless the optional **exhaustive** mode is on).
3. Download the PDF / JATS XML to disk, extract the text and persist it in
   `reference_full_text`. Claude chat, AI summaries and full-text search
   pick the article up automatically — exactly as if a human had uploaded it.
4. Record every attempt in `retrieval_attempts` and update the consolidated
   `reference_fulltext_status` row for the references list status dot.

A background **worker** (`bin/worker.php`, driven by cron) drains the
`retrieval_queue` so bulk operations don't block HTTP requests.

## Sources (catalog of 9)

| Source | Best for | Identifier needed | Auth |
|---|---|---|---|
| **PMC (NCBI)** | Biomedical OA | DOI / PMID → PMCID | Email; optional encrypted API key (10 req/s) |
| **Europe PMC** | Biomedical OA, EU mirror | DOI / PMID | Polite email in User-Agent |
| **Unpaywall** | Widest OA PDF coverage | DOI | Email (no API key) |
| **OpenAlex** | General academic | DOI / PMID | Email (`mailto=`) optional |
| **Semantic Scholar** | Citations + TLDR | DOI / PMID | Optional encrypted API key |
| **bioRxiv / medRxiv** | Biomedical preprints | DOI in `10.1101/` | None |
| **arXiv** | Physics / CS / quant-bio preprints | arXiv DOI or `arxiv.org` URL | None (1 req / 3 s) |
| **CrossRef** | Canonical metadata + fallback PDF links | DOI | Polite email |
| **DOAJ** | Open Access journals index | DOI | None |

OpenAIRE is intentionally not shipped — file an issue if you need it.

## Configuration

Everything is admin-driven via **Admin → Full-text (APIs)**:

- **Global toggle** to enable the module.
- **Mode**: `manual`, `on import`, `scheduled`.
- **Concurrency**, **timeout per reference**, **retry-after-days**.
- **Exhaustive** mode (query every source even after one succeeds).
- **Auto-download PDF/XML** (on by default).
- **Priority order** (textarea, one source per line — valid names are
  `pmc`, `europepmc`, `unpaywall`, `openalex`, `semantic_scholar`,
  `biorxiv`, `arxiv`, `crossref`, `doaj`).
- **Polite-pool email** used as the default `mailto:` for the User-Agent
  (`SysRevAI/{version} (+{site_url}; mailto:{email})`).
- **Per-source card**: enable toggle, source-specific email, optional API
  key (PMC, Semantic Scholar), and **Verify connection** button.
- **Test the chain** form: enter a DOI/PMID and see the live trace
  without writing to the database.

## Background worker

Add this cron entry on the server so the queue drains automatically:

```cron
* * * * * php /path/to/sysrevai/bin/worker.php >> /path/to/sysrevai/storage/logs/worker.log 2>&1
```

The worker is `flock`-guarded, exits after ~50 seconds, and becomes a
no-op when the module is disabled.

## Rate limiting

Every API call goes through `Services\FullTextRetrieval\RateLimiter`,
which keeps a single row per source in `rate_limit_counters` and resets
the window atomically. When a source has spent its budget the worker
silently skips it for that tick (status `rate_limited`) — no API ever
sees a flood from SysRevAI.

## Where things live

```
src/Services/FullTextRetrieval/
├── FullTextSourceInterface.php
├── FullTextResult.php          # value object
├── BaseHttpSource.php          # cURL + polite UA + retries + rate limiter
├── FullTextRetrievalService.php# orchestrator
├── FullTextDownloader.php      # PDF + JATS download / parse / persist
├── JatsParser.php              # PMC / EPMC JATS XML reader
├── RateLimiter.php             # per-source budgets
└── <source>Source.php          # one per API
bin/worker.php                  # cron-driven queue drainer
storage/pdfs/                   # downloaded PDFs (0600, gitignored)
storage/xml/                    # downloaded JATS XML (0600, gitignored)
```

Schema additions: `retrieval_attempts`, `reference_fulltext_status`,
`retrieval_queue` (migration `015`), and `rate_limit_counters`
(migration `016`).

## Legal and ethical scope

SysRevAI only uses **official APIs**. It does **not** bypass paywalls,
scrape publisher HTML, or interface with Sci-Hub-style services. For
non-OA articles the user must rely on their **own institutional
licences** to obtain the PDF and upload it manually from the full-text
view. SysRevAI is not responsible for misuse by end users.
