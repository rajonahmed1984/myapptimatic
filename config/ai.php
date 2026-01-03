<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),
    'license_risk_enabled' => (bool) env('AI_LICENSE_RISK_ENABLED', false),
    'churn_enabled' => (bool) env('AI_CHURN_ENABLED', false),
    'sync_health_enabled' => (bool) env('AI_SYNC_HEALTH_ENABLED', false),
    'require_signed_verify' => (bool) env('AI_REQUIRE_SIGNED_VERIFY', false),

    'license_risk_url' => env('AI_LICENSE_RISK_URL'),
    'churn_url' => env('AI_CHURN_URL'),
    'sync_health_url' => env('AI_SYNC_HEALTH_URL'),

    'token' => env('AI_SERVICE_TOKEN'),
    'hmac_secret' => env('AI_HMAC_SECRET'),
    'timeout' => (int) env('AI_HTTP_TIMEOUT', 5),
];
