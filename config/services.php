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

    'wallester' => [
        'base_url' => env('WALLESTER_BASE_URL'),
        'api_key' => env('WALLESTER_API_KEY'),
        'private_key' => env('WALLESTER_PRIVATE_KEY'), // Coloque a chave privada PEM aqui ou use variável de ambiente
        'product_code' => env('WALLESTER_PRODUCT_CODE'),
        'audit_source_type' => env('WALLESTER_AUDIT_SOURCE_TYPE', 'Backend'),
        'card_rsa_private_key' => env('WALLESTER_CARD_RSA_PRIVATE_KEY'),
        '3ds_challenge_ttl_seconds' => (int) env('WALLESTER_3DS_CHALLENGE_TTL', 300),
    ],

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

];
