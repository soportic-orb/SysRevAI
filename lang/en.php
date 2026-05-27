<?php

declare(strict_types=1);

/*
 * Core UI translations — English.
 */

return [
    'app' => [
        'name'    => 'SysRevAI',
        'tagline' => 'AI-powered systematic reviews',
    ],

    'nav' => [
        'dashboard'     => 'Dashboard',
        'reviews'       => 'Reviews',
        'settings'      => 'Settings',
        'notifications' => 'Notifications',
        'profile'       => 'Profile',
        'logout'        => 'Log out',
    ],

    'auth' => [
        'login_title'         => 'Sign in',
        'email'               => 'Email',
        'password'            => 'Password',
        'sign_in'             => 'Sign in',
        'invalid_credentials' => 'Invalid credentials. Please try again.',
        'welcome'             => 'Welcome back',
    ],

    'dashboard' => [
        'title'        => 'Dashboard',
        'greeting'     => 'Hello, %s',
        'subtitle'     => 'An overview of your review activity.',
        'no_reviews'   => 'You don\'t have any reviews yet.',
        'create_first' => 'Create your first review',
        'metrics'      => [
            'imported'     => 'Imported',
            'duplicates'   => 'Duplicates',
            'ta_screening' => 'Title/Abstract',
            'ft_screening' => 'Full text',
            'included'     => 'Included',
            'excluded'     => 'Excluded',
        ],
    ],

    'footer' => [
        'powered_by' => 'Open source (AGPL-3.0)',
        'support'    => 'Support the project',
    ],

    'errors' => [
        '404_title' => 'Page not found',
        '404_text'  => 'The page you are looking for does not exist or has moved.',
        '403_title' => 'Access denied',
        '403_text'  => 'You do not have permission to view this page.',
        'back_home' => 'Back to home',
    ],
];
