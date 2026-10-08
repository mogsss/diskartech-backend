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
    
    'gemini' => [
        'key' => env('GEMINI_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'matching_timeout' => (int) env('GEMINI_MATCHING_TIMEOUT', 15),
        'matching_connect_timeout' => (int) env('GEMINI_MATCHING_CONNECT_TIMEOUT', 5),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'matching_enabled' => env('OPENAI_MATCHING_ENABLED', true),
        'matching_timeout' => (int) env('OPENAI_MATCHING_TIMEOUT', 5),
        'matching_quota_cooldown' => (int) env('OPENAI_MATCHING_QUOTA_COOLDOWN', 1800),
    ],

    'job_matching' => [
        'fallback_cache_seconds' => (int) env('JOB_MATCHING_FALLBACK_CACHE_SECONDS', 60),
    ],

];
