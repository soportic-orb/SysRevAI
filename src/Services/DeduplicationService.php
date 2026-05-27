<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Models\Duplicate;
use SysRevAI\Models\Reference;

/**
 * Deduplication levels 1 (exact) and 2 (fuzzy).
 *
 * Level 1 — exact match by DOI, PMID, or normalized key (title + first author
 * + year): the later record is marked `duplicate` automatically.
 * Level 2 — fuzzy Jaro-Winkler on normalized titles (default threshold 0.92):
 * recorded as a pending candidate for manual/semantic (Phase 7) resolution.
 */
final class DeduplicationService
{
    public const FUZZY_THRESHOLD = 0.92;

    /** Normalize a title for comparison: lowercase, deburr, strip punctuation. */
    public static function normalizeTitle(string $title): string
    {
        $t = mb_strtolower(trim($title));
        $t = self::deburr($t);
        $t = preg_replace('/[^a-z0-9 ]+/', ' ', $t) ?? '';
        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    /** Stable level-1 key: normalized title | first-author surname | year. */
    public static function dedupKey(array $ref): string
    {
        $title = self::normalizeTitle((string) ($ref['title'] ?? ''));
        $author = '';
        if (!empty($ref['authors'][0])) {
            $first = (string) $ref['authors'][0];
            $author = self::normalizeTitle(preg_split('/[,\s]+/', $first)[0] ?? '');
        }
        $year = (string) ($ref['year'] ?? '');
        return $title === '' ? '' : "{$title}|{$author}|{$year}";
    }

    private static function deburr(string $s): string
    {
        $map = ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
                'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
                'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c'];
        return strtr($s, $map);
    }

    /** Jaro-Winkler similarity in [0,1]. */
    public static function jaroWinkler(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        $la = strlen($a);
        $lb = strlen($b);
        if ($la === 0 || $lb === 0) {
            return 0.0;
        }

        $window = (int) floor(max($la, $lb) / 2) - 1;
        $window = max($window, 0);
        $aMatch = array_fill(0, $la, false);
        $bMatch = array_fill(0, $lb, false);
        $matches = 0;

        for ($i = 0; $i < $la; $i++) {
            $start = max(0, $i - $window);
            $end = min($i + $window + 1, $lb);
            for ($j = $start; $j < $end; $j++) {
                if (!$bMatch[$j] && $a[$i] === $b[$j]) {
                    $aMatch[$i] = true;
                    $bMatch[$j] = true;
                    $matches++;
                    break;
                }
            }
        }
        if ($matches === 0) {
            return 0.0;
        }

        $t = 0;
        $k = 0;
        for ($i = 0; $i < $la; $i++) {
            if ($aMatch[$i]) {
                while (!$bMatch[$k]) {
                    $k++;
                }
                if ($a[$i] !== $b[$k]) {
                    $t++;
                }
                $k++;
            }
        }
        $t /= 2;

        $m = (float) $matches;
        $jaro = (($m / $la) + ($m / $lb) + (($m - $t) / $m)) / 3;

        // Winkler prefix bonus (up to 4 chars).
        $prefix = 0;
        $max = min(4, $la, $lb);
        for ($i = 0; $i < $max; $i++) {
            if ($a[$i] === $b[$i]) {
                $prefix++;
            } else {
                break;
            }
        }
        return $jaro + $prefix * 0.1 * (1 - $jaro);
    }

    /**
     * Run levels 1 and 2 over a review's references.
     * @return array{exact:int,fuzzy:int}
     */
    public static function run(int $reviewId): array
    {
        $refs = Reference::forDedup($reviewId);

        $seenDoi = [];
        $seenPmid = [];
        $seenKey = [];
        $exact = 0;

        // Level 1 — exact.
        foreach ($refs as &$ref) {
            $id = (int) $ref['id'];
            $doi = (string) $ref['doi'];
            $pmid = (string) $ref['pmid'];
            $key = (string) $ref['dedup_key'];
            $canonical = null;
            $method = null;

            if ($doi !== '' && isset($seenDoi[$doi])) {
                $canonical = $seenDoi[$doi];
                $method = 'doi';
            } elseif ($pmid !== '' && isset($seenPmid[$pmid])) {
                $canonical = $seenPmid[$pmid];
                $method = 'pmid';
            } elseif ($key !== '' && isset($seenKey[$key])) {
                $canonical = $seenKey[$key];
                $method = 'title';
            }

            if ($canonical !== null) {
                Reference::setStatus($id, 'duplicate');
                Duplicate::record($reviewId, $canonical, $id, $method, 1.0, 'confirmed', 'Exact match (' . $method . ')');
                $ref['_dup'] = true;
                $exact++;
                continue;
            }

            if ($doi !== '') $seenDoi[$doi] = $id;
            if ($pmid !== '') $seenPmid[$pmid] = $id;
            if ($key !== '') $seenKey[$key] = $id;
            $ref['_dup'] = false;
        }
        unset($ref);

        // Level 2 — fuzzy on titles, bucketed by year to bound the comparisons.
        $buckets = [];
        foreach ($refs as $ref) {
            if (!empty($ref['_dup'])) {
                continue;
            }
            $buckets[(string) $ref['year']][] = [
                'id'    => (int) $ref['id'],
                'title' => self::normalizeTitle((string) $ref['title']),
            ];
        }

        $fuzzy = 0;
        foreach ($buckets as $bucket) {
            $n = count($bucket);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($bucket[$i]['title'] === '' || $bucket[$j]['title'] === '') {
                        continue;
                    }
                    $sim = self::jaroWinkler($bucket[$i]['title'], $bucket[$j]['title']);
                    if ($sim >= self::FUZZY_THRESHOLD) {
                        Duplicate::record(
                            $reviewId,
                            $bucket[$i]['id'],
                            $bucket[$j]['id'],
                            'fuzzy',
                            round($sim, 3),
                            'pending',
                            'Fuzzy title match'
                        );
                        $fuzzy++;
                    }
                }
            }
        }

        return ['exact' => $exact, 'fuzzy' => $fuzzy];
    }
}
