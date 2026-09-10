<?php

return [
    // Defaults for the SMS gateway (24bulksmsbd.com). Admin > Settings > SMS
    // overrides these once saved. Provide keys via environment; avoid
    // committing real values.
    'enabled' => env('SMS_ENABLED', false),
    'api_url' => env('SMS_API_URL', 'https://www.24bulksmsbd.com/api/smsSendApi'),
    'customer_id' => env('SMS_CUSTOMER_ID'),
    'api_key' => env('SMS_API_KEY'),
    'whitelisted_ip' => env('SMS_WHITELISTED_IP'),
    'timeout' => (int) env('SMS_TIMEOUT', 15),
];
