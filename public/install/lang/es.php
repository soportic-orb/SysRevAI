<?php

declare(strict_types=1);

/*
 * Installer translations — Spanish.
 */

return [
    'lang_name'        => 'Español',
    'app_name'         => 'SysRevAI',
    'installer_title'  => 'Instalador de SysRevAI',
    'progress'         => 'Paso %d de %d',

    'nav' => [
        'next'          => 'Siguiente',
        'back'          => 'Anterior',
        'begin'         => 'Comenzar la instalación',
        'recheck'       => 'Volver a comprobar',
        'finish'        => 'Finalizar',
    ],

    'common' => [
        'status_ok'     => 'Correcto',
        'status_fail'   => 'Error',
        'status_warn'   => 'Aviso',
        'detected'      => 'Detectado',
        'required'      => 'Requerido',
        'recommended'   => 'Recomendado',
        'coming_soon'   => 'Este paso se implementará en una fase posterior. Por ahora sirve como estructura del asistente.',
    ],

    'steps' => [
        0 => 'Bienvenida',
        1 => 'Requisitos del sistema',
        2 => 'Dependencias',
        3 => 'Base de datos',
        4 => 'Creación de tablas',
        5 => 'Configuración general',
        6 => 'Usuario administrador',
        7 => 'Finalización',
    ],

    'step0' => [
        'welcome_title'    => 'Bienvenido/a a SysRevAI',
        'welcome_subtitle' => 'Plataforma self-hosted para revisiones sistemáticas de literatura científica, potenciada con IA.',
        'welcome_intro'    => 'Este asistente te guiará paso a paso por la instalación. No necesitas configurar nada manualmente ni tener Composer instalado para empezar.',
        'choose_language'  => 'Idioma del instalador',
        'note_no_keys'     => 'No se pedirán claves de API durante la instalación. Las integraciones (Claude, Google Translate, SMTP) se configuran después desde el panel de administración.',
    ],

    'step1' => [
        'title'          => 'Comprobación de los requisitos del sistema',
        'intro'          => 'Comprobamos que tu servidor cumple los requisitos mínimos. No podrás continuar hasta que todo esté en verde (los avisos no bloquean).',
        'group_php'      => 'Versión de PHP',
        'group_ext'      => 'Extensiones de PHP',
        'group_write'    => 'Permisos de escritura',
        'group_limits'   => 'Límites de PHP',
        'php_version'    => 'PHP ≥ 8.2',
        'mem_limit'      => 'Memoria (memory_limit) ≥ 128 MB',
        'upload_size'    => 'Tamaño máximo de subida (upload_max_filesize) ≥ 50 MB',
        'post_size'      => 'Tamaño máximo de POST (post_max_size) ≥ 50 MB',
        'exec_time'      => 'Tiempo máximo de ejecución (max_execution_time) ≥ 60 s',
        'all_good'       => '¡Todo listo! Tu servidor cumple los requisitos.',
        'fix_needed'     => 'Hay que solucionar algunos requisitos antes de continuar.',
        'fix_ext'        => 'Instala la extensión en tu php.ini o mediante el gestor de paquetes del sistema (p. ej. apt install php-%s) y reinicia PHP.',
        'fix_write'      => 'Da permisos de escritura al directorio (p. ej. chmod -R 775 y el propietario adecuado para el usuario del servidor web).',
        'fix_ini'        => 'Edita esta directiva en tu php.ini y reinicia PHP-FPM/Apache.',
        'unlimited'      => 'sin límite',
    ],
];
