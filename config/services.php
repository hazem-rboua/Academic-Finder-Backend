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

    // ADDITIVE 2026-06-16 — Academic Finder PDF generation
    'af_frontend' => [
        'url' => env('AF_FRONTEND_URL', 'https://academicfinder.twindix.com'),
    ],
    'browsershot' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY', '/opt/alt/alt-nodejs20/root/usr/bin/node'),
        'npm_binary'  => env('BROWSERSHOT_NPM_BINARY', '/opt/alt/alt-nodejs20/root/usr/bin/npm'),
    ],

    'ai_api' => [
        'base_url' => env('AI_API_BASE_URL', 'https://acdmic-ai.twindix.com'),
        'timeout' => (int) env('AI_API_TIMEOUT', 60),
        'retry_times' => (int) env('AI_API_RETRY_TIMES', 3),
        'enabled' => env('AI_API_ENABLED', true),
    ],

];
