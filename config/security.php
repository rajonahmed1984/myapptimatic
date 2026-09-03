<?php

/**
 * Security switches that used to be read with env() at request time. Under
 * `php artisan config:cache` Laravel stops loading .env, so those calls silently
 * returned their defaults — which turned signature verification off in
 * production with nothing in the logs to say so.
 */
return [
    'license_verify' => [
        // Include invoice/payment detail in the verification response. Legacy
        // clients depend on it; new integrations should not.
        'legacy_response' => (bool) env('COMPAT_LEGACY_LICENSE_RESPONSE', true),
        'require_signature' => (bool) env('AI_REQUIRE_SIGNED_VERIFY', false),
        'secret' => env('AI_VERIFY_SECRET'),
        'signature_tolerance_seconds' => (int) env('API_SIGNATURE_TOLERANCE_SECONDS', 300),
    ],

    'ai' => [
        'license_risk_enabled' => (bool) env('AI_LICENSE_RISK_ENABLED', false),
    ],

    'cron' => [
        'ip_allowlist' => (string) env('CRON_IP_ALLOWLIST', ''),
        'hmac_secret' => env('CRON_HMAC_SECRET'),
        'signature_tolerance_seconds' => (int) env('CRON_SIGNATURE_TOLERANCE_SECONDS', 300),
    ],

    // NOTE: TRUSTED_PROXIES is deliberately NOT read here. TrustProxies::proxies()
    // runs while bootstrap/app.php wires middleware, before the config
    // repository exists, so that one has to stay on env().
];
