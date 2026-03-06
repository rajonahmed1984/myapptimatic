<?php

return [
    // Central sender routing by category.
    'billing' => [
        'address' => 'billing@apptimatic.com',
        'name' => 'Apptimatic Billing',
    ],
    'support' => [
        'address' => 'support@apptimatic.com',
        'name' => 'Apptimatic Support',
    ],
    'system' => [
        'address' => 'noreply@apptimatic.com',
        'name' => 'Apptimatic',
    ],

    // Classification guide:
    // - billing: invoice / payment / license billing / finance notices
    // - support: support tickets / project chat / support replies
    // - system: task status / auth / cron / operational alerts / default

    // Keep false in production to avoid storing full message bodies in system logs.
    'log_bodies' => (bool) env('SYSTEM_MAIL_LOG_BODIES', false),

    // Comma-separated deny list for addresses that consistently bounce.
    'suppressed_recipients' => array_values(array_filter(array_map(
        static fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('SYSTEM_MAIL_SUPPRESSED_RECIPIENTS', ''))
    ))),

    // Single destination for all master-admin/admin internal notifications.
    'master_admin_notification_email' => strtolower(trim((string) env(
        'SYSTEM_MAIL_MASTER_ADMIN_NOTIFICATION_EMAIL',
        'arsp.bdinfo@gmail.com'
    ))),
];
