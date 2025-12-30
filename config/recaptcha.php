<?php

return [
    'enabled' => env('RECAPTCHA_ENABLED', env('APP_ENV') !== 'local'),
    // Provide keys via environment; avoid committing real values
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'project_id' => env('RECAPTCHA_PROJECT_ID'),
    'api_key' => env('RECAPTCHA_API_KEY'),
    'score_threshold' => env('RECAPTCHA_SCORE_THRESHOLD', 0.5),
];
