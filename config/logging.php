<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$daily = static function (string $directory, string $filename, int $days = 30): array {
    if (env('APP_ENV') === 'testing') {
        return [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ];
    }

    return [
        'driver' => 'daily',
        'path' => storage_path("logs/{$directory}/{$filename}"),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => $days,
        'permission' => 0664,
        'replace_placeholders' => true,
    ];
};

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    | Pastas criadas em AppServiceProvider / logs:fix-permissions.
    */
    'directories' => [
        'app',
        'auth',
        'customer',
        'ebury',
        'kyc',
        'merchant',
        'queue',
        'sms',
        'stripe',
        'wallester',
        'fx',
        'whatsapp',
        'reward',
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/app/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'permission' => 0664,
            'replace_placeholders' => true,
        ],

        'daily' => $daily('app', 'laravel.log', (int) env('LOG_DAILY_DAYS', 14)),

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/app/laravel.log'),
        ],

        'stripe' => $daily('stripe', 'stripe.log'),
        'wallester' => $daily('wallester', 'wallester.log'),
        'fx' => $daily('fx', 'fx.log'),
        'sms' => $daily('sms', 'sms.log'),
        'whatsapp' => $daily('whatsapp', 'whatsapp.log'),
        'reward' => $daily('reward', 'reward.log'),
        'ebury' => $daily('ebury', 'ebury.log'),
        'merchant' => $daily('merchant', 'merchant.log'),

        'diditDocumentJob' => $daily('kyc', 'didit-document.log'),
        'diditUploadDocumentMediaJob' => $daily('kyc', 'didit-upload.log'),
        'createDiditSession' => $daily('kyc', 'didit-session.log'),

        'authRegister' => $daily('auth', 'register.log'),

        'customerUploadDocument' => $daily('customer', 'upload-document.log'),
        'corporateDocumentUpload' => $daily('customer', 'corporate-document.log'),

    ],

];
