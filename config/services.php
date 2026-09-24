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

    'didit' => [
        'api_key' => env('DIDIT_API_KEY'),
        'api_secret' => env('DIDIT_API_SECRET'),
        'base_url' => env('DIDIT_BASE_URL'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'workflow_kyb_id' => env('DIDIT_WORKFLOW_KYB_ID'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
    ],

    'wallester' => [],

    'provisioning' => [
        'tries' => env('PROVISIONING_TRIES', 3),
        'backoff' => [60, 300, 900],
    ],

    'ebury' => [
        'simulate' => env('EBURY_SIMULATE', true),
        'simulate_auto_approve' => env('EBURY_SIMULATE_AUTO_APPROVE', true),
        'simulate_auto_approve_delay' => env('EBURY_SIMULATE_AUTO_APPROVE_DELAY', 3),
        'simulate_use_queue' => env('EBURY_SIMULATE_USE_QUEUE', false),
    ],

    'fx' => [
        'base_url' => env('FX_BASE_URL', 'https://economia.awesomeapi.com.br'),
        'api_key' => env('FX_API_KEY'),
        'cache_ttl_seconds' => env('FX_CACHE_TTL', 60),
        'percentage' => env('FX_PERCENTAGE', ''),
    ],

    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_BASE_URL', env('EVOLUTION_BASE_URL', '')),
        'api_key' => env('WHATSAPP_API_KEY', env('EVOLUTION_API_KEY', '')),
        'instance' => env('WHATSAPP_INSTANCE', env('EVOLUTION_INSTANCE', '')),
        'timeout_seconds' => env('WHATSAPP_TIMEOUT', 15),
    ],

];
