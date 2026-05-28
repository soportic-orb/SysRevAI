<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Risk-of-bias tools, their domains and their judgement vocabularies.
 *
 * Each judgement maps to a "level" (low → high) used to colour the
 * traffic-light plot and to drive the summary chart.
 */
final class RiskOfBiasService
{
    public const TOOLS = ['rob2', 'robins_i', 'newcastle_ottawa', 'jbi'];

    /** Judgement levels in increasing severity for sorting / colour. */
    private const LEVELS = [
        'low'            => ['order' => 1, 'color' => '#009e73'], // green
        'some_concerns'  => ['order' => 2, 'color' => '#e69f00'], // amber
        'moderate'       => ['order' => 2, 'color' => '#e69f00'],
        'high'           => ['order' => 3, 'color' => '#d55e00'], // red
        'serious'        => ['order' => 3, 'color' => '#d55e00'],
        'critical'       => ['order' => 4, 'color' => '#9c1a00'],
        'no_information' => ['order' => 0, 'color' => '#7a8a99'],
        'yes'            => ['order' => 1, 'color' => '#009e73'],
        'no'             => ['order' => 3, 'color' => '#d55e00'],
        'unclear'        => ['order' => 2, 'color' => '#e69f00'],
        'na'             => ['order' => 0, 'color' => '#dde3e9'],
    ];

    /** @return array<string,array{label_key:string,domains:array<int,string>,judgements:array<int,string>}> */
    public static function tools(): array
    {
        return [
            'rob2' => [
                'label_key'  => 'rob.tool_rob2',
                'domains'    => ['randomization', 'deviations', 'missing_data', 'measurement', 'reported_result', 'overall'],
                'judgements' => ['low', 'some_concerns', 'high', 'no_information'],
            ],
            'robins_i' => [
                'label_key'  => 'rob.tool_robins_i',
                'domains'    => ['confounding', 'selection', 'classification', 'deviations', 'missing_data', 'measurement', 'reported_result', 'overall'],
                'judgements' => ['low', 'moderate', 'serious', 'critical', 'no_information'],
            ],
            'newcastle_ottawa' => [
                'label_key'  => 'rob.tool_newcastle_ottawa',
                'domains'    => ['selection', 'comparability', 'outcome', 'overall'],
                'judgements' => ['low', 'some_concerns', 'high', 'no_information'],
            ],
            'jbi' => [
                'label_key'  => 'rob.tool_jbi',
                'domains'    => ['inclusion', 'exposure', 'outcome', 'confounders', 'analysis', 'overall'],
                'judgements' => ['yes', 'no', 'unclear', 'na'],
            ],
        ];
    }

    public static function knownTool(string $tool): bool
    {
        return array_key_exists($tool, self::tools());
    }

    public static function tool(string $tool): ?array
    {
        return self::tools()[$tool] ?? null;
    }

    public static function color(string $judgement): string
    {
        return self::LEVELS[$judgement]['color'] ?? '#dde3e9';
    }

    public static function level(string $judgement): int
    {
        return self::LEVELS[$judgement]['order'] ?? 0;
    }

    /** Validate that a judgement belongs to a tool's vocabulary. */
    public static function isValidJudgement(string $tool, string $judgement): bool
    {
        $def = self::tool($tool);
        return $def !== null && in_array($judgement, $def['judgements'], true);
    }

    /** Validate a domain for a tool. */
    public static function isValidDomain(string $tool, string $domain): bool
    {
        $def = self::tool($tool);
        return $def !== null && in_array($domain, $def['domains'], true);
    }
}
