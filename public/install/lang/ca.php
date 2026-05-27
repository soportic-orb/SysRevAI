<?php

declare(strict_types=1);

/*
 * Installer translations — Catalan (default).
 * Independent from the core i18n on purpose: the installer must work before
 * the application (and Composer) is set up.
 */

return [
    'lang_name'        => 'Català',
    'app_name'         => 'SysRevAI',
    'installer_title'  => 'Instal·lador de SysRevAI',
    'progress'         => 'Pas %d de %d',

    'nav' => [
        'next'          => 'Següent',
        'back'          => 'Anterior',
        'begin'         => 'Començar la instal·lació',
        'recheck'       => 'Tornar a comprovar',
        'finish'        => 'Finalitzar',
    ],

    'common' => [
        'status_ok'     => 'Correcte',
        'status_fail'   => 'Error',
        'status_warn'   => 'Avís',
        'detected'      => 'Detectat',
        'required'      => 'Requerit',
        'recommended'   => 'Recomanat',
        'coming_soon'   => 'Aquest pas s\'implementarà en una fase posterior. De moment serveix com a estructura del assistent.',
    ],

    'steps' => [
        0 => 'Benvinguda',
        1 => 'Requisits del sistema',
        2 => 'Dependències',
        3 => 'Base de dades',
        4 => 'Creació de taules',
        5 => 'Configuració general',
        6 => 'Usuari administrador',
        7 => 'Finalització',
    ],

    'step0' => [
        'welcome_title'    => 'Benvingut/da a SysRevAI',
        'welcome_subtitle' => 'Plataforma self-hosted per a revisions sistemàtiques de literatura científica, potenciada amb IA.',
        'welcome_intro'    => 'Aquest assistent et guiarà pas a pas per la instal·lació. No cal configurar res manualment ni tenir Composer instal·lat per començar.',
        'choose_language'  => 'Idioma de l\'instal·lador',
        'note_no_keys'     => 'No es demanaran claus d\'API durant la instal·lació. Les integracions (Claude, Google Translate, SMTP) es configuren després des del panell d\'administració.',
    ],

    'step1' => [
        'title'          => 'Comprovació dels requisits del sistema',
        'intro'          => 'Revisem que el teu servidor compleix els requisits mínims. No podràs continuar fins que tot estigui en verd (els avisos no bloquegen).',
        'group_php'      => 'Versió de PHP',
        'group_ext'      => 'Extensions de PHP',
        'group_write'    => 'Permisos d\'escriptura',
        'group_limits'   => 'Límits de PHP',
        'php_version'    => 'PHP ≥ 8.2',
        'mem_limit'      => 'Memòria (memory_limit) ≥ 128 MB',
        'upload_size'    => 'Mida màxima de pujada (upload_max_filesize) ≥ 50 MB',
        'post_size'      => 'Mida màxima de POST (post_max_size) ≥ 50 MB',
        'exec_time'      => 'Temps màxim d\'execució (max_execution_time) ≥ 60 s',
        'all_good'       => 'Tot a punt! El teu servidor compleix els requisits.',
        'fix_needed'     => 'Cal solucionar alguns requisits abans de continuar.',
        'fix_ext'        => 'Instal·la l\'extensió al teu php.ini o mitjançant el gestor de paquets del sistema (p. ex. apt install php-%s) i reinicia PHP.',
        'fix_write'      => 'Dóna permisos d\'escriptura al directori (p. ex. chmod -R 775 i el propietari adequat per a l\'usuari del servidor web).',
        'fix_ini'        => 'Edita aquesta directiva al teu php.ini i reinicia PHP-FPM/Apache.',
        'unlimited'      => 'sense límit',
    ],
];
