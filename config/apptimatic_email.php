<?php

return [
    'allow_admin_global_mailboxes' => (bool) env('APPTIMATIC_EMAIL_ADMIN_GLOBAL', false),
    'validation_interval_minutes' => (int) env('APPTIMATIC_EMAIL_VALIDATION_MINUTES', 1),
    'remember_days' => (int) env('APPTIMATIC_EMAIL_REMEMBER_DAYS', 30),
    'persistent_login_days' => (int) env('APPTIMATIC_EMAIL_PERSISTENT_LOGIN_DAYS', 3650),
    'inbox_refresh_seconds' => (int) env('APPTIMATIC_EMAIL_INBOX_REFRESH_SECONDS', 60),

    'imap' => [
        'host' => env('APPTIMATIC_EMAIL_IMAP_HOST', ''),
        'port' => (int) env('APPTIMATIC_EMAIL_IMAP_PORT', 993),
        'encryption' => env('APPTIMATIC_EMAIL_IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('APPTIMATIC_EMAIL_IMAP_VALIDATE_CERT', true),
    ],

    // Optional bootstrap dataset for mailbox provisioning.
    // Use `php artisan mail:bootstrap --dry-run` to preview.
    'bootstrap' => [
        'mailboxes' => [
            // [
            //     'email' => 'support@company.com',
            //     'display_name' => 'Support Inbox',
            //     'imap_host' => 'imap.company.com',
            //     'imap_port' => 993,
            //     'imap_encryption' => 'ssl',
            //     'imap_validate_cert' => true,
            //     'status' => 'active',
            // ],
        ],
        'assignments' => [
            // [
            //     'mailbox_email' => 'support@company.com',
            //     'actor' => [
            //         'type' => 'support', // user|support|employee|sales_rep
            //         'email' => 'support.user@company.com',
            //     ],
            //     'can_read' => true,
            //     'can_manage' => false,
            // ],
        ],
    ],
];
