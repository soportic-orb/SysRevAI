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
];
