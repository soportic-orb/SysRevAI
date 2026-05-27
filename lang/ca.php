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

    'admin' => [
        'title' => 'Configuració',
        'save'  => 'Desar',
        'saved' => 'Canvis desats correctament.',
        'sections' => [
            'general'  => 'General',
            'claude'   => 'Claude (IA)',
            'security' => 'Seguretat',
            'about'    => 'Sobre / Llicència',
        ],
        'general' => [
            'site_name'      => 'Nom del lloc',
            'default_locale' => 'Idioma per defecte',
            'timezone'       => 'Zona horària',
            'accent_color'   => 'Color d\'accent',
            'theme'          => 'Tema visual',
            'theme_light'    => 'Clar',
            'theme_dark'     => 'Fosc',
            'theme_auto'     => 'Automàtic',
            'footer_text'    => 'Text del peu de pàgina',
            'show_branding'  => 'Mostrar "Powered by SysRevAI" al peu',
        ],
        'claude' => [
            'intro'             => 'Configura la integració amb l\'API de Claude (Anthropic). La clau es desa xifrada.',
            'api_key'           => 'Clau d\'API',
            'api_key_help'      => 'Deixa-ho en blanc per mantenir la clau actual.',
            'key_set'           => 'configurada',
            'model_complex'     => 'Model per a tasques complexes',
            'model_light'       => 'Model per a tasques lleugeres',
            'temperature'       => 'Temperatura',
            'max_tokens'        => 'Màxim de tokens per resposta',
            'monthly_limit'     => 'Límit de cost mensual (USD)',
            'monthly_limit_help' => '0 = sense límit. En superar-lo, les funcions d\'IA es desactiven.',
            'features'          => 'Funcions d\'IA actives',
            'feature_summaries' => 'Resums automàtics',
            'feature_screening' => 'Suggeriments de cribratge',
            'feature_extraction' => 'Extracció de dades',
            'feature_bias'      => 'Avaluació del risc de biaix',
            'feature_chat'      => 'Xat amb l\'article',
            'feature_dedup'     => 'Deduplicació semàntica',
            'verify'            => 'Verificar connexió',
        ],
        'security' => [
            'min_password_length' => 'Longitud mínima de contrasenya',
            'session_lifetime'    => 'Temps de sessió (minuts)',
            'max_login_attempts'  => 'Intents màxims d\'inici de sessió',
            'lockout_minutes'     => 'Durada del bloqueig (minuts)',
            'two_factor'          => 'Autenticació de dos factors (2FA)',
            'tfa_disabled'        => 'Desactivada',
            'tfa_optional'        => 'Opcional',
            'tfa_required'        => 'Obligatòria',
            'force_https'         => 'Forçar HTTPS',
        ],
        'about' => [
            'version'       => 'Versió instal·lada',
            'license'       => 'Llicència',
            'repo'          => 'Repositori',
            'support_title' => 'Donar suport a l\'autor',
            'support_text'  => 'SysRevAI és un projecte de codi obert mantingut per un investigador en el seu temps lliure. Si et resulta útil, pots donar-li suport amb una donació voluntària.',
            'donate_btn'    => 'Donar',
            'show_mention'  => 'Mostrar la menció a l\'autor al tauler',
        ],
    ],
];
