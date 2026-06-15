<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Field schemas for the two review registries SysRevAI supports.
 *
 * PROSPERO (CRD, University of York) — systematic reviews. Field set
 * is modelled on the public registration records the user shared,
 * e.g. https://www.crd.york.ac.uk/PROSPERO/view/CRD420251125617.
 *
 * OSF (Open Science Framework) — scoping reviews preregistered as
 * JBI / PRISMA-ScR-aligned components, e.g. https://osf.io/duq2g.
 *
 * Each field carries:
 *   id       string  Stable key used in the JSON blob, the form and
 *                    the AI fill payload.
 *   label    string  Translation key under "registration.fields".
 *   type     string  'text' (single-line) or 'textarea' (multi-line).
 *   hint?    string  Optional translation key for the help text.
 *   rows?    int     Optional textarea row hint.
 *
 * Translations live in lang/{ca,es,en}.php under the
 * "registration.fields.*" namespace.
 */
final class RegistrationFields
{
    public const KIND_PROSPERO = 'prospero';
    public const KIND_OSF      = 'osf';

    /**
     * Pick the schema by review type.
     *
     * @return array<int,array{id:string,label:string,type:string,hint?:string,rows?:int}>
     */
    public static function schemaFor(string $kind): array
    {
        return $kind === self::KIND_OSF ? self::osf() : self::prospero();
    }

    /** @return array<int,array{id:string,label:string,type:string,hint?:string,rows?:int}> */
    public static function prospero(): array
    {
        return [
            ['id' => 'title',                  'label' => 'title',                  'type' => 'text'],
            ['id' => 'original_language_title','label' => 'original_language_title','type' => 'text'],
            ['id' => 'anticipated_start',      'label' => 'anticipated_start',      'type' => 'text'],
            ['id' => 'anticipated_completion', 'label' => 'anticipated_completion', 'type' => 'text'],
            ['id' => 'stage_of_review',        'label' => 'stage_of_review',        'type' => 'textarea', 'rows' => 2],
            ['id' => 'named_contact',          'label' => 'named_contact',          'type' => 'text'],
            ['id' => 'named_contact_email',    'label' => 'named_contact_email',    'type' => 'text'],
            ['id' => 'organisation',           'label' => 'organisation',           'type' => 'textarea', 'rows' => 2],
            ['id' => 'country',                'label' => 'country',                'type' => 'text'],
            ['id' => 'review_team',            'label' => 'review_team',            'type' => 'textarea', 'rows' => 3],
            ['id' => 'collaborator',           'label' => 'collaborator',           'type' => 'textarea', 'rows' => 2],
            ['id' => 'funding_sources',        'label' => 'funding_sources',        'type' => 'textarea', 'rows' => 2],
            ['id' => 'conflicts_of_interest',  'label' => 'conflicts_of_interest',  'type' => 'textarea', 'rows' => 2],
            ['id' => 'review_question',        'label' => 'review_question',        'type' => 'textarea', 'rows' => 3],
            ['id' => 'searches',               'label' => 'searches',               'type' => 'textarea', 'rows' => 4],
            ['id' => 'url_search_strategy',    'label' => 'url_search_strategy',    'type' => 'text'],
            ['id' => 'condition_domain',       'label' => 'condition_domain',       'type' => 'textarea', 'rows' => 2],
            ['id' => 'participants',           'label' => 'participants',           'type' => 'textarea', 'rows' => 2],
            ['id' => 'interventions',          'label' => 'interventions',          'type' => 'textarea', 'rows' => 2],
            ['id' => 'comparators',            'label' => 'comparators',            'type' => 'textarea', 'rows' => 2],
            ['id' => 'types_of_studies',       'label' => 'types_of_studies',       'type' => 'textarea', 'rows' => 2],
            ['id' => 'context',                'label' => 'context',                'type' => 'textarea', 'rows' => 2],
            ['id' => 'main_outcomes',          'label' => 'main_outcomes',          'type' => 'textarea', 'rows' => 3],
            ['id' => 'additional_outcomes',    'label' => 'additional_outcomes',    'type' => 'textarea', 'rows' => 3],
            ['id' => 'data_extraction',        'label' => 'data_extraction',        'type' => 'textarea', 'rows' => 3],
            ['id' => 'risk_of_bias',           'label' => 'risk_of_bias',           'type' => 'textarea', 'rows' => 3],
            ['id' => 'data_synthesis',         'label' => 'data_synthesis',         'type' => 'textarea', 'rows' => 3],
            ['id' => 'subgroup_analysis',      'label' => 'subgroup_analysis',      'type' => 'textarea', 'rows' => 2],
            ['id' => 'type_method',            'label' => 'type_method',            'type' => 'textarea', 'rows' => 2],
            ['id' => 'dissemination_plans',    'label' => 'dissemination_plans',    'type' => 'textarea', 'rows' => 2],
            ['id' => 'keywords',               'label' => 'keywords',               'type' => 'textarea', 'rows' => 2],
            ['id' => 'language',               'label' => 'language',               'type' => 'text'],
        ];
    }

    /** @return array<int,array{id:string,label:string,type:string,hint?:string,rows?:int}> */
    public static function osf(): array
    {
        return [
            ['id' => 'title',                'label' => 'title',                'type' => 'text'],
            ['id' => 'description',          'label' => 'description',          'type' => 'textarea', 'rows' => 4],
            ['id' => 'categories',           'label' => 'categories',           'type' => 'text'],
            ['id' => 'tags',                 'label' => 'tags',                 'type' => 'textarea', 'rows' => 2],
            ['id' => 'background',           'label' => 'background',           'type' => 'textarea', 'rows' => 5],
            ['id' => 'objectives',           'label' => 'objectives',           'type' => 'textarea', 'rows' => 3],
            ['id' => 'review_questions',     'label' => 'review_questions',     'type' => 'textarea', 'rows' => 3],
            ['id' => 'population',           'label' => 'population',           'type' => 'textarea', 'rows' => 2],
            ['id' => 'concept',              'label' => 'concept',              'type' => 'textarea', 'rows' => 2],
            ['id' => 'context_scoping',      'label' => 'context_scoping',      'type' => 'textarea', 'rows' => 2],
            ['id' => 'types_of_sources',     'label' => 'types_of_sources',     'type' => 'textarea', 'rows' => 2],
            ['id' => 'eligibility_criteria', 'label' => 'eligibility_criteria', 'type' => 'textarea', 'rows' => 3],
            ['id' => 'information_sources',  'label' => 'information_sources',  'type' => 'textarea', 'rows' => 3],
            ['id' => 'search_strategy',      'label' => 'search_strategy',      'type' => 'textarea', 'rows' => 4],
            ['id' => 'source_selection',     'label' => 'source_selection',     'type' => 'textarea', 'rows' => 3],
            ['id' => 'data_charting',        'label' => 'data_charting',        'type' => 'textarea', 'rows' => 3],
            ['id' => 'critical_appraisal',   'label' => 'critical_appraisal',   'type' => 'textarea', 'rows' => 2],
            ['id' => 'synthesis_results',    'label' => 'synthesis_results',    'type' => 'textarea', 'rows' => 3],
            ['id' => 'contributors',         'label' => 'contributors',         'type' => 'textarea', 'rows' => 3],
            ['id' => 'funding',              'label' => 'funding',              'type' => 'textarea', 'rows' => 2],
            ['id' => 'conflicts_of_interest','label' => 'conflicts_of_interest','type' => 'textarea', 'rows' => 2],
            ['id' => 'license',              'label' => 'license',              'type' => 'text'],
        ];
    }

    /**
     * Restrict the user-supplied form payload to the keys defined in
     * the schema so a malicious client can't sneak in arbitrary keys.
     *
     * @param  array<string,mixed> $input
     * @return array<string,string>
     */
    public static function sanitise(array $input, string $kind): array
    {
        $out = [];
        foreach (self::schemaFor($kind) as $f) {
            $val = (string) ($input[$f['id']] ?? '');
            // Cap every textarea at 16k chars; PROSPERO itself caps at
            // ~6k per field, but we leave room for the user to type
            // freely before they realise it should be shorter.
            $out[$f['id']] = mb_substr(trim($val), 0, 16000);
        }
        return $out;
    }
}
