<?php

declare(strict_types=1);

/*
 * Installer translations — English.
 */

return [
    'lang_name'        => 'English',
    'app_name'         => 'SysRevAI',
    'installer_title'  => 'SysRevAI Installer',
    'progress'         => 'Step %d of %d',

    'nav' => [
        'next'          => 'Next',
        'back'          => 'Back',
        'begin'         => 'Start installation',
        'recheck'       => 'Re-check',
        'finish'        => 'Finish',
    ],

    'common' => [
        'status_ok'     => 'OK',
        'status_fail'   => 'Failed',
        'status_warn'   => 'Warning',
        'detected'      => 'Detected',
        'required'      => 'Required',
        'recommended'   => 'Recommended',
        'coming_soon'   => 'This step will be implemented in a later phase. For now it serves as the wizard scaffold.',
    ],

    'steps' => [
        0 => 'Welcome',
        1 => 'System requirements',
        2 => 'Dependencies',
        3 => 'Database',
        4 => 'Create tables',
        5 => 'General settings',
        6 => 'Administrator account',
        7 => 'Finish',
    ],

    'step0' => [
        'welcome_title'    => 'Welcome to SysRevAI',
        'welcome_subtitle' => 'A self-hosted platform for systematic literature reviews, powered by AI.',
        'welcome_intro'    => 'This wizard will guide you through the installation step by step. You do not need to configure anything by hand or have Composer installed to begin.',
        'choose_language'  => 'Installer language',
        'note_no_keys'     => 'No API keys are requested during installation. Integrations (Claude, Google Translate, SMTP) are configured afterwards from the admin panel.',
    ],

    'step1' => [
        'title'          => 'System requirements check',
        'intro'          => 'We are verifying that your server meets the minimum requirements. You cannot continue until everything is green (warnings do not block).',
        'group_php'      => 'PHP version',
        'group_ext'      => 'PHP extensions',
        'group_write'    => 'Write permissions',
        'group_limits'   => 'PHP limits',
        'php_version'    => 'PHP ≥ 8.2',
        'mem_limit'      => 'Memory (memory_limit) ≥ 128 MB',
        'upload_size'    => 'Max upload size (upload_max_filesize) ≥ 50 MB',
        'post_size'      => 'Max POST size (post_max_size) ≥ 50 MB',
        'exec_time'      => 'Max execution time (max_execution_time) ≥ 60 s',
        'all_good'       => 'All set! Your server meets the requirements.',
        'fix_needed'     => 'Some requirements must be resolved before continuing.',
        'fix_ext'        => 'Install the extension in your php.ini or via your system package manager (e.g. apt install php-%s) and restart PHP.',
        'fix_write'      => 'Grant write permissions to the directory (e.g. chmod -R 775 and the correct owner for the web-server user).',
        'fix_ini'        => 'Edit this directive in your php.ini and restart PHP-FPM/Apache.',
        'unlimited'      => 'unlimited',
    ],
];
