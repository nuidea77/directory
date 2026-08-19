<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    /*
    | verify.mn — Mongolia-only Mobile-Originated (MO) SMS phone verification.
    | The user sends the code TO the shortcode (144773); verify.mn confirms it.
    */
    'verify_mn' => [
        'base_url' => env('VERIFY_MN_BASE_URL', 'https://api.verify.mn'),
        'api_key' => env('VERIFY_MN_API_KEY'),
        // Set false in local/dev to auto-verify without calling the real API.
        'enabled' => env('VERIFY_MN_ENABLED', true),
    ],

    /*
    | byl.mn — Mongolian payment aggregator (QPay, SocialPay, Pocket, Golomt).
    */
    'byl' => [
        'base_url' => env('BYL_BASE_URL', 'https://byl.mn/api'),
        'token' => env('BYL_API_TOKEN'),
        'project_id' => env('BYL_PROJECT_ID'),
        'webhook_secret' => env('BYL_WEBHOOK_SECRET'),
    ],

];
