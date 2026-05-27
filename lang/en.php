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

    'admin' => [
        'title' => 'Settings',
        'save'  => 'Save',
        'saved' => 'Changes saved.',
        'sections' => [
            'general'  => 'General',
            'claude'   => 'Claude (AI)',
            'security' => 'Security',
            'about'    => 'About / License',
        ],
        'general' => [
            'site_name'      => 'Site name',
            'default_locale' => 'Default language',
            'timezone'       => 'Timezone',
            'accent_color'   => 'Accent color',
            'theme'          => 'Theme',
            'theme_light'    => 'Light',
            'theme_dark'     => 'Dark',
            'theme_auto'     => 'Auto',
            'footer_text'    => 'Footer text',
            'show_branding'  => 'Show "Powered by SysRevAI" in the footer',
        ],
        'claude' => [
            'intro'             => 'Configure the Claude API (Anthropic) integration. The key is stored encrypted.',
            'api_key'           => 'API key',
            'api_key_help'      => 'Leave blank to keep the current key.',
            'key_set'           => 'set',
            'model_complex'     => 'Model for complex tasks',
            'model_light'       => 'Model for light tasks',
            'temperature'       => 'Temperature',
            'max_tokens'        => 'Max tokens per response',
            'monthly_limit'     => 'Monthly cost limit (USD)',
            'monthly_limit_help' => '0 = no limit. When exceeded, AI features are disabled.',
            'features'          => 'Active AI features',
            'feature_summaries' => 'Automatic summaries',
            'feature_screening' => 'Screening suggestions',
            'feature_extraction' => 'Data extraction',
            'feature_bias'      => 'Risk-of-bias assessment',
            'feature_chat'      => 'Chat with article',
            'feature_dedup'     => 'Semantic deduplication',
            'verify'            => 'Verify connection',
        ],
        'security' => [
            'min_password_length' => 'Minimum password length',
            'session_lifetime'    => 'Session timeout (minutes)',
            'max_login_attempts'  => 'Max login attempts',
            'lockout_minutes'     => 'Lockout duration (minutes)',
            'two_factor'          => 'Two-factor authentication (2FA)',
            'tfa_disabled'        => 'Disabled',
            'tfa_optional'        => 'Optional',
            'tfa_required'        => 'Required',
            'force_https'         => 'Force HTTPS',
        ],
        'about' => [
            'version'       => 'Installed version',
            'license'       => 'License',
            'repo'          => 'Repository',
            'support_title' => 'Support the author',
            'support_text'  => 'SysRevAI is an open-source project maintained by a researcher in their spare time. If you find it useful, you can support it with a voluntary donation.',
            'donate_btn'    => 'Donate',
            'show_mention'  => 'Show the author mention on the dashboard',
        ],
    ],
];
