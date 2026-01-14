<?php

return [
    'provider_url' => env('CURRENCY_RATE_URL', 'https://open.er-api.com/v6/latest/USD'),
    'cache_ttl_minutes' => (int) env('CURRENCY_RATE_TTL', 60),
    'timeout_seconds' => (int) env('CURRENCY_RATE_TIMEOUT', 5),
    'retry' => (int) env('CURRENCY_RATE_RETRY', 1),
    'retry_delay_ms' => (int) env('CURRENCY_RATE_RETRY_DELAY', 200),
    'cache_key' => 'currency.usd_bdt_rate',
    'fallback' => [
        'usd_bdt' => 105.50,
    ],
];
