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
];

