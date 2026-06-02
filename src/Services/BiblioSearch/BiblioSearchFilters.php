<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * Source-agnostic eligibility filters derived from the review protocol
 * or supplied directly by the user from the search form. Immutable and
 * read-only — callers never mutate one, they pass it down and the
 * adapters cherry-pick the fields they can express to their API.
 *
 * Most adapters will only honour the year range; the rest is a hint the
 * user can apply post-hoc on the merged result set. Consensus is the
 * one source that natively expresses every field, so it gets the
 * highest-fidelity filtering "for free".
 */
final class BiblioSearchFilters
{
    /** Consensus's `study_types` enum — the canonical vocabulary every
     *  caller should use when mapping a protocol description to a value. */
    public const STUDY_TYPES = [
        'case report',
        'literature review',
        'meta-analysis',
        'non-rct experimental',
        'non-rct in vitro',
        'non-rct observational study',
        'rct',
        'systematic review',
        'animal',
    ];

    /**
     * @param list<string> $studyTypes  Subset of self::STUDY_TYPES.
     */
    public function __construct(
        public readonly ?int $yearMin = null,
        public readonly ?int $yearMax = null,
        public readonly ?bool $human = null,
        public readonly ?int $sampleSizeMin = null,
        public readonly ?int $sjrMax = null,
        public readonly array $studyTypes = [],
        public readonly ?bool $excludePreprints = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->yearMin === null
            && $this->yearMax === null
            && $this->human === null
            && $this->sampleSizeMin === null
            && $this->sjrMax === null
            && $this->studyTypes === []
            && $this->excludePreprints === null;
    }

    /**
     * ISO-8601 bounds for the year range, suitable for the date filters
     * exposed by CrossRef / OpenAlex. Returns null on each side that
     * wasn't set so the adapter can skip half of the filter cleanly.
     *
     * @return array{0:?string,1:?string}
     */
    public function yearRangeIso(): array
    {
        $lo = $this->yearMin !== null ? sprintf('%04d-01-01', $this->yearMin) : null;
        $hi = $this->yearMax !== null ? sprintf('%04d-12-31', $this->yearMax) : null;
        return [$lo, $hi];
    }

    /**
     * Build a filter set from the search-form controls. Empty / invalid
     * values become null, the study-types array is filtered through
     * self::STUDY_TYPES, and the numeric bounds are sanity-checked so a
     * user can't push the request into nonsense territory.
     *
     * @param array<string,mixed> $in
     */
    public static function fromArray(array $in): self
    {
        $thisYear = (int) date('Y');
        $intOr = static function (mixed $v, int $min, int $max): ?int {
            if ($v === null || $v === '' || (is_string($v) && trim($v) === '')) {
                return null;
            }
            if (!is_numeric($v)) {
                return null;
            }
            $i = (int) $v;
            return $i < $min || $i > $max ? null : $i;
        };
        $boolOr = static function (mixed $v): ?bool {
            if ($v === null || $v === '' || (is_string($v) && trim($v) === '')) {
                return null;
            }
            // Form checkboxes arrive as "1" / "0" / "on" / "off"; explicit
            // "0" / "false" / "no" maps to false so the caller can mean
            // "definitely not". Anything truthy else → true.
            if (is_string($v)) {
                $v = strtolower(trim($v));
                if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
                    return false;
                }
                return $v !== '';
            }
            return (bool) $v;
        };

        $types = [];
        $raw = $in['study_types'] ?? [];
        if (is_string($raw)) {
            $raw = [$raw];
        }
        if (is_array($raw)) {
            foreach ($raw as $t) {
                if (!is_string($t)) {
                    continue;
                }
                $t = strtolower(trim($t));
                if ($t !== '' && in_array($t, self::STUDY_TYPES, true)) {
                    $types[] = $t;
                }
            }
        }
        $types = array_values(array_unique($types));

        return new self(
            yearMin:          $intOr($in['year_min']        ?? null, 1800, $thisYear + 2),
            yearMax:          $intOr($in['year_max']        ?? null, 1800, $thisYear + 2),
            human:            $boolOr($in['human']          ?? null),
            sampleSizeMin:    $intOr($in['sample_size_min'] ?? null, 1, 10_000_000),
            sjrMax:           $intOr($in['sjr_max']         ?? null, 1, 4),
            studyTypes:       $types,
            excludePreprints: $boolOr($in['exclude_preprints'] ?? null),
        );
    }

    /**
     * Best-effort projection from a review row to a starting filter set.
     * We only fill what we can confidently read from the protocol:
     *
     *   - the PICO `study_design` text is matched against multilingual
     *     keywords (ca/es/en) and mapped to the Consensus enum;
     *
     * Numeric bounds, the human / preprint / SJR flags and the sample-
     * size minimum are left null because the protocol doesn't record
     * them. The user can still override anything from the form.
     *
     * @param array<string,mixed> $review
     */
    public static function fromProtocol(array $review): self
    {
        $pico = \SysRevAI\Models\Review::pico($review);
        $design = mb_strtolower(trim((string) ($pico['study_design'] ?? '')));
        if ($design === '') {
            return new self();
        }

        $types = [];
        // Order matters: longer / more specific phrases first so an
        // umbrella term like "review" doesn't grab the systematic-review
        // hit. Substring match keeps the rule readable.
        $rules = [
            'systematic review'           => ['systematic review', 'revisión sistemática', 'revisio sistemàtica', 'revisió sistemàtica'],
            'meta-analysis'               => ['meta-analysis', 'metaanalysis', 'metaanálisis', 'metaanàlisi'],
            'literature review'           => ['literature review', 'revisión narrativa', 'revisión bibliográfica', 'revisió narrativa', 'revisió bibliogràfica'],
            'rct'                         => ['randomized controlled trial', 'randomised controlled trial', 'rct', 'ensayo clínico aleatorizado', 'ensayo aleatorizado', 'assaig clínic aleatoritzat', 'eca'],
            'non-rct in vitro'            => ['in vitro'],
            'animal'                      => ['animal study', 'in vivo', 'animal model'],
            'case report'                 => ['case report', 'reporte de caso', 'caso clínico', 'cas clínic'],
            'non-rct observational study' => ['cohort', 'cohorte', 'case-control', 'casos y controles', 'casos i controls', 'cross-sectional', 'transversal', 'observational', 'observacional'],
            'non-rct experimental'        => ['quasi-experimental', 'cuasi-experimental', 'quasi experimental', 'experimental'],
        ];
        foreach ($rules as $enum => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($design, $needle)) {
                    $types[] = $enum;
                    break;
                }
            }
        }
        $types = array_values(array_unique($types));

        return new self(studyTypes: $types);
    }
}
