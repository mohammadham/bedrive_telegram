<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN') ?? env('STORAGE_TELEGRAM_BOT_TOKEN'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID') ?? env('STORAGE_TELEGRAM_CHANNEL_ID'),
        'api_id' => env('TELEGRAM_API_ID') ?? env('STORAGE_TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH') ?? env('STORAGE_TELEGRAM_API_HASH'),
        'phone' => env('TELEGRAM_PHONE') ?? env('STORAGE_TELEGRAM_PHONE'),
        'session_file' => env('TELEGRAM_SESSION_FILE') ?? env('STORAGE_TELEGRAM_SESSION_FILE', storage_path('app/telegram/session.madeline')),
    ],
];
