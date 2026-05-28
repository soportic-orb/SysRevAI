<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Contract every academic full-text source implements. Sources are pure
 * lookups: they validate their config, decide whether they can answer for a
 * given reference, and return a FullTextResult (or null if not found).
 *
 * Rate limiting, retries and HTTP plumbing live in BaseHttpSource so concrete
 * implementations stay small.
 */
interface FullTextSourceInterface
{
    /** Machine-readable identifier — also the settings prefix (e.g. "unpaywall"). */
    public function name(): string;

    /** True if the source is enabled in the admin panel and properly configured. */
    public function isEnabled(): bool;

    /** Can the source handle this reference at all (e.g. needs a DOI)? */
    public function supports(array $reference): bool;

    /**
     * Look the reference up. Returns null when the source cannot find anything;
     * throws only on programmer errors — transport / API errors are returned as
     * null with the failure surfaced through the orchestrator's attempt log.
     */
    public function retrieve(array $reference): ?FullTextResult;

    /**
     * Light health-check used by the admin "Verify connection" button.
     * @return array{ok:bool,message:string}
     */
    public function verifyConnection(): array;

    /**
     * Documented rate limit hint (informational, used by the rate limiter).
     * @return array{requests:int,per_seconds:int}
     */
    public function rateLimit(): array;
}
