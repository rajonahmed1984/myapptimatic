<?php

return [
    'api_url' => env('WHMCS_API_URL', ''),
    'url' => env('WHMCS_URL', ''),
    'username' => env('WHMCS_ADMIN_USERNAME', ''),
    'identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'secret' => env('WHMCS_API_SECRET', ''),
    'timeout' => env('WHMCS_TIMEOUT', 10),
];
