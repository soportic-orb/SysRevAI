<?php

declare(strict_types=1);

/**
 * PARTIAL French locale. French is NOT a UI language (it's absent from
 * config('supported_locales')), so this file only ships the namespaces
 * needed to render user-facing DOCUMENTS in French — today that's the
 * article critical report, whose content language the user picks at
 * generation time. Section headings in the web view and the Word/PDF
 * exports resolve through I18n::getIn('fr', …), which falls back to the
 * session locale for any key not present here.
 */
return [
    'articles' => [
        'critical' => [
            'generated_at' => 'Généré le %s.',
            'h_summary'           => 'Résumé exécutif',
            'h_devils_advocate'   => 'Avocat du diable',
            'h_recommendations'   => 'Recommandations par section',
            'axis_methodology' => 'Méthodologie',
            'axis_clarity'     => 'Clarté',
            'axis_novelty'     => 'Nouveauté',
            'axis_evidence'    => 'Preuves',
            'axis_limitations' => 'Limites',
            'h_key_strengths'        => 'Points forts',
            'h_key_weaknesses'       => 'Points faibles',
            'h_methodology_critique' => 'Critique méthodologique',
            'h_statistical_concerns' => 'Préoccupations statistiques',
            'h_ethical_concerns'     => 'Considérations éthiques',
            'h_reproducibility'      => 'Reproductibilité',
            'h_literature_positioning' => 'Positionnement dans la littérature',
            'h_publication_outlook'  => 'Perspectives de publication',
            'priority_high'   => 'Haute',
            'priority_medium' => 'Moyenne',
            'priority_low'    => 'Basse',
        ],
        'export' => [
            'doc_subtitle'  => 'Rapport critique généré par SysRevAI.',
            'h_scores'      => 'Scores',
            'h_chat'        => 'Conversation avec l\'IA',
            'chat_subtitle' => 'Transcription ordonnée du plus ancien au plus récent.',
            'who_assistant' => 'Assistant',
            'who_user'      => 'Vous',
        ],
    ],
];
