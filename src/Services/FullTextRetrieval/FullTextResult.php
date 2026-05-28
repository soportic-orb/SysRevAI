<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Outcome of a single source lookup. Sources never download the PDF themselves
 * (they just return a URL and the orchestrator decides whether to fetch it).
 */
final class FullTextResult
{
    public function __construct(
        public string $source,
        public ?string $pdfUrl = null,
        public ?string $pdfBinary = null,
        public ?string $xmlContent = null,
        public ?string $textContent = null,
        public string $licenseType = 'unknown',
        public string $version = 'unknown',
        public array $metadata = [],
        public float $confidence = 1.0,
    ) {
    }

    /** True if this result actually carries some retrievable content. */
    public function hasContent(): bool
    {
        return ($this->pdfUrl !== null && $this->pdfUrl !== '')
            || ($this->pdfBinary !== null && $this->pdfBinary !== '')
            || ($this->xmlContent !== null && $this->xmlContent !== '')
            || ($this->textContent !== null && $this->textContent !== '');
    }
}
