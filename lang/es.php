<?php

declare(strict_types=1);

/*
 * Core UI translations — Spanish.
 */

return [
    'app' => [
        'name'    => 'SysRevAI',
        'tagline' => 'Revisiones sistemáticas potenciadas con IA',
    ],

    'nav' => [
        'dashboard'     => 'Panel',
        'reviews'       => 'Revisiones',
        'settings'      => 'Configuración',
        'notifications' => 'Notificaciones',
        'profile'       => 'Perfil',
        'logout'        => 'Cerrar sesión',
    ],

    'auth' => [
        'login_title'         => 'Inicia sesión',
        'email'               => 'Correo electrónico',
        'password'            => 'Contraseña',
        'sign_in'             => 'Entrar',
        'invalid_credentials' => 'Credenciales incorrectas. Inténtalo de nuevo.',
        'welcome'             => 'Bienvenido/a de nuevo',
    ],

    'dashboard' => [
        'title'        => 'Panel',
        'greeting'     => 'Hola, %s',
        'subtitle'     => 'Resumen de tu actividad de revisión.',
        'no_reviews'   => 'Todavía no tienes ninguna revisión.',
        'create_first' => 'Crear la primera revisión',
        'metrics'      => [
            'imported'     => 'Importados',
            'duplicates'   => 'Duplicados',
            'ta_screening' => 'Cribado T/R',
            'ft_screening' => 'Texto completo',
            'included'     => 'Incluidos',
            'excluded'     => 'Excluidos',
        ],
    ],

    'footer' => [
        'powered_by' => 'Código abierto (AGPL-3.0)',
        'support'    => 'Apoyar el proyecto',
    ],

    'errors' => [
        '404_title' => 'Página no encontrada',
        '404_text'  => 'La página que buscas no existe o se ha movido.',
        '403_title' => 'Acceso denegado',
        '403_text'  => 'No tienes permisos para ver esta página.',
        'back_home' => 'Volver al inicio',
    ],

    'admin' => [
        'title' => 'Configuración',
        'save'  => 'Guardar',
        'saved' => 'Cambios guardados correctamente.',
        'sections' => [
            'general'  => 'General',
            'claude'   => 'Claude (IA)',
            'security' => 'Seguridad',
            'about'    => 'Acerca de / Licencia',
        ],
        'general' => [
            'site_name'      => 'Nombre del sitio',
            'default_locale' => 'Idioma por defecto',
            'timezone'       => 'Zona horaria',
            'accent_color'   => 'Color de acento',
            'theme'          => 'Tema visual',
            'theme_light'    => 'Claro',
            'theme_dark'     => 'Oscuro',
            'theme_auto'     => 'Automático',
            'footer_text'    => 'Texto del pie de página',
            'show_branding'  => 'Mostrar "Powered by SysRevAI" en el pie',
        ],
        'claude' => [
            'intro'             => 'Configura la integración con la API de Claude (Anthropic). La clave se guarda cifrada.',
            'api_key'           => 'Clave de API',
            'api_key_help'      => 'Déjalo en blanco para mantener la clave actual.',
            'key_set'           => 'configurada',
            'model_complex'     => 'Modelo para tareas complejas',
            'model_light'       => 'Modelo para tareas ligeras',
            'temperature'       => 'Temperatura',
            'max_tokens'        => 'Máximo de tokens por respuesta',
            'monthly_limit'     => 'Límite de coste mensual (USD)',
            'monthly_limit_help' => '0 = sin límite. Al superarlo, las funciones de IA se desactivan.',
            'features'          => 'Funciones de IA activas',
            'feature_summaries' => 'Resúmenes automáticos',
            'feature_screening' => 'Sugerencias de cribado',
            'feature_extraction' => 'Extracción de datos',
            'feature_bias'      => 'Evaluación del riesgo de sesgo',
            'feature_chat'      => 'Chat con el artículo',
            'feature_dedup'     => 'Deduplicación semántica',
            'verify'            => 'Verificar conexión',
        ],
        'security' => [
            'min_password_length' => 'Longitud mínima de contraseña',
            'session_lifetime'    => 'Tiempo de sesión (minutos)',
            'max_login_attempts'  => 'Intentos máximos de inicio de sesión',
            'lockout_minutes'     => 'Duración del bloqueo (minutos)',
            'two_factor'          => 'Autenticación de dos factores (2FA)',
            'tfa_disabled'        => 'Desactivada',
            'tfa_optional'        => 'Opcional',
            'tfa_required'        => 'Obligatoria',
            'force_https'         => 'Forzar HTTPS',
        ],
        'about' => [
            'version'       => 'Versión instalada',
            'license'       => 'Licencia',
            'repo'          => 'Repositorio',
            'support_title' => 'Apoyar al autor',
            'support_text'  => 'SysRevAI es un proyecto open source mantenido por un investigador en su tiempo libre. Si te resulta útil, puedes apoyarlo con una donación voluntaria.',
            'donate_btn'    => 'Donar',
            'show_mention'  => 'Mostrar la mención al autor en el panel',
        ],
    ],
];
