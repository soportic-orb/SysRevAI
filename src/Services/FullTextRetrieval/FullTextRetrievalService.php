<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullTextStatus;
use SysRevAI\Models\RetrievalAttempt;

/**
 * Orchestrator that walks the configured chain of academic full-text sources.
 *
 * Stops at the first source that produces a downloadable URL or XML, unless
 * the admin enabled "exhaustive" mode (then every enabled source is queried
 * and every successful result is recorded for the reviewer to choose).
 */
final class FullTextRetrievalService
{
    /** Built-in source classes that ship with this PR. */
    public const SOURCE_MAP = [
        'unpaywall' => UnpaywallSource::class,
        'europepmc' => EuropePmcSource::class,
        'pmc'       => PmcSource::class,
        'openalex'  => OpenAlexSource::class,
    ];

    /** Fallback priority order when the admin hasn't customised it. */
    public const DEFAULT_PRIORITY = ['pmc', 'europepmc', 'unpaywall', 'openalex'];

    /** @var array<int,FullTextSourceInterface> */
    private array $sources;

    public function __construct(?array $sources = null)
    {
        $this->sources = $sources ?? self::loadConfiguredSources();
    }

    /**
     * @return array{success:bool,attempts:array<int,array>,result:?FullTextResult}
     */
    public function retrieveFor(int $referenceId, bool $exhaustive = false): array
    {
        $reference = Reference::find($referenceId);
        if ($reference === null) {
            return ['success' => false, 'attempts' => [], 'result' => null];
        }
        return $this->runChain($reference, $exhaustive, persist: true);
    }

    /**
     * Synthetic dry-run for the admin "Test chain" form. No DB writes.
     * @return array{success:bool,attempts:array<int,array>,result:?FullTextResult}
     */
    public function testChain(?string $doi, ?string $pmid): array
    {
        $reference = ['id' => 0, 'doi' => $doi, 'pmid' => $pmid, 'title' => null];
        return $this->runChain($reference, exhaustive: false, persist: false);
    }

    /**
     * @return array<int,array{name:string,enabled:bool,ok:bool,message:string}>
     */
    public function verifyAll(): array
    {
        $out = [];
        foreach (self::loadAllSources() as $source) {
            $check = $source->verifyConnection();
            $out[] = [
                'name'    => $source->name(),
                'enabled' => $source->isEnabled(),
                'ok'      => (bool) $check['ok'],
                'message' => (string) $check['message'],
            ];
        }
        return $out;
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    /**
     * @return array{success:bool,attempts:array<int,array>,result:?FullTextResult}
     */
    private function runChain(array $reference, bool $exhaustive, bool $persist): array
    {
        $attempts = [];
        $best = null;

        foreach ($this->sources as $source) {
            if (!$source->isEnabled()) {
                continue;
            }
            $start = microtime(true);
            $supports = $source->supports($reference);
            $result = $supports ? $source->retrieve($reference) : null;
            $ms = (int) round((microtime(true) - $start) * 1000);

            $status = match (true) {
                !$supports                                 => 'not_found',
                $result instanceof FullTextResult
                    && $result->hasContent()               => 'success',
                $result === null                           => 'not_found',
                default                                    => 'error',
            };

            $attempt = [
                'source'           => $source->name(),
                'status'           => $status,
                'response_time_ms' => $ms,
                'pdf_found'        => $status === 'success' && $result !== null && $result->pdfUrl !== null,
                'pdf_url'          => $result->pdfUrl ?? null,
                'license_type'     => $result->licenseType ?? null,
                'version_type'     => $result->version ?? null,
            ];
            $attempts[] = $attempt;

            if ($persist) {
                RetrievalAttempt::record((int) ($reference['id'] ?? 0), $attempt);
            }

            if ($status === 'success') {
                $best = $best ?? $result;
                if (!$exhaustive) {
                    break;
                }
            }
        }

        if ($persist && (int) ($reference['id'] ?? 0) > 0) {
            ReferenceFullTextStatus::upsert(
                (int) $reference['id'],
                $best instanceof FullTextResult,
                $best?->source,
                $best?->pdfUrl,
                $best?->licenseType,
                $best?->version,
                $this->retryWindowDays(),
                attemptDelta: count($attempts)
            );
        }

        return [
            'success'  => $best instanceof FullTextResult,
            'attempts' => $attempts,
            'result'   => $best,
        ];
    }

    /** @return array<int,FullTextSourceInterface> Sources enabled by the admin, in priority order. */
    public static function loadConfiguredSources(): array
    {
        $priority = self::priorityOrder();
        $sources = [];
        foreach ($priority as $name) {
            $class = self::SOURCE_MAP[$name] ?? null;
            if ($class === null) {
                continue;
            }
            /** @var FullTextSourceInterface $instance */
            $instance = new $class();
            $sources[] = $instance;
        }
        return $sources;
    }

    /** @return array<int,FullTextSourceInterface> Every known source (enabled or not), in priority order. */
    public static function loadAllSources(): array
    {
        $priority = self::priorityOrder();
        $out = [];
        foreach ($priority as $name) {
            $class = self::SOURCE_MAP[$name] ?? null;
            if ($class !== null) {
                $out[] = new $class();
            }
        }
        return $out;
    }

    /** @return string[] */
    public static function priorityOrder(): array
    {
        $configured = (array) (setting('fulltext.priority') ?? []);
        $valid = array_values(array_filter(
            $configured,
            static fn ($n) => is_string($n) && isset(self::SOURCE_MAP[$n])
        ));
        if ($valid === []) {
            $valid = self::DEFAULT_PRIORITY;
        }
        // Append any known source the user didn't include yet, preserving the
        // recommended default order so the fallback is deterministic.
        $rest = array_merge(self::DEFAULT_PRIORITY, array_keys(self::SOURCE_MAP));
        foreach ($rest as $name) {
            if (!in_array($name, $valid, true) && isset(self::SOURCE_MAP[$name])) {
                $valid[] = $name;
            }
        }
        return $valid;
    }

    private function retryWindowDays(): int
    {
        return max(1, (int) (setting('fulltext.retry_after_days') ?? 30));
    }
}
