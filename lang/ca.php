<?php

declare(strict_types=1);

/*
 * Core UI translations — Catalan (default).
 * A later phase layers DB-backed, community-editable overrides on top of these.
 */

return [
    'app' => [
        'name'    => 'SysRevAI',
        'tagline' => 'Revisions sistemàtiques potenciades amb IA',
    ],

    'nav' => [
        'dashboard'     => 'Tauler',
        'reviews'       => 'Revisions',
        'settings'      => 'Configuració',
        'notifications' => 'Notificacions',
        'profile'       => 'Perfil',
        'logout'        => 'Tancar sessió',
    ],

    'auth' => [
        'login_title'         => 'Inicia la sessió',
        'email'               => 'Correu electrònic',
        'password'            => 'Contrasenya',
        'sign_in'             => 'Entrar',
        'invalid_credentials' => 'Credencials incorrectes. Torna-ho a provar.',
        'welcome'             => 'Benvingut/da de nou',
    ],

    'dashboard' => [
        'title'        => 'Tauler',
        'greeting'     => 'Hola, %s',
        'subtitle'     => 'Resum de la teva activitat de revisió.',
        'no_reviews'   => 'Encara no tens cap revisió.',
        'create_first' => 'Crear la primera revisió',
        'metrics'      => [
            'imported'     => 'Importats',
            'duplicates'   => 'Duplicats',
            'ta_screening' => 'Cribratge T/R',
            'ft_screening' => 'Text complet',
            'included'     => 'Inclosos',
            'excluded'     => 'Exclosos',
        ],
    ],

    'footer' => [
        'powered_by' => 'Codi obert (AGPL-3.0)',
        'support'    => 'Donar suport al projecte',
    ],

    'errors' => [
        '404_title' => 'Pàgina no trobada',
        '404_text'  => 'La pàgina que busques no existeix o s\'ha mogut.',
        '403_title' => 'Accés denegat',
        '403_text'  => 'No tens permisos per veure aquesta pàgina.',
        'back_home' => 'Tornar a l\'inici',
    ],
];
