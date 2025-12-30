<?php

return [
    'enabled' => env('RECAPTCHA_ENABLED', env('APP_ENV') !== 'local'),
    'site_key' => env('RECAPTCHA_SITE_KEY', '6LcSHTssAAAAAGi2fLTNuxXDxiyCsRXW22nrxtPF'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY', '6LcSHTssAAAAAET9L4ir879EZ5v3189GHj9CZ6vd'),
    'project_id' => env('RECAPTCHA_PROJECT_ID', 'keen-defender-419018'),
    'api_key' => env('RECAPTCHA_API_KEY'),
    'score_threshold' => env('RECAPTCHA_SCORE_THRESHOLD', 0.5),
];
