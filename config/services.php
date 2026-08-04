<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bkash' => [
        'api_key' => env('BKASH_BRIDGE_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'license_cert' => [
        // RSA keypair (PEM, base64-encoded to stay single-line env-safe), generated once
        // offline — never in the DB. Signs the offline license certificate payload so a
        // licensed product can verify it without calling home. Generate with:
        //   openssl genrsa -out private.pem 2048
        //   openssl rsa -in private.pem -pubout -out public.pem
        //   base64 -w0 private.pem   (and public.pem)
        'private_key' => env('LICENSE_CERT_PRIVATE_KEY'),
        'public_key' => env('LICENSE_CERT_PUBLIC_KEY'),
        'key_id' => env('LICENSE_CERT_KEY_ID', 'v1'),
    ],

];
