<?php

/**
 * Gera yoop-api.postman_collection.json (só API Yoop, sem Wallester).
 *
 * Uso: php postman/scripts/generate-yoop-collection.php
 */

$baseUrl = 'https://dashboard.yooppayments.eu/api';

$jsonHeaders = [
    ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
    ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
];

$acceptHeader = [
    ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
];

$noAuth = ['type' => 'noauth'];

$saveToken = [[
    'listen' => 'test',
    'script' => [
        'type' => 'text/javascript',
        'exec' => [
            'var json = pm.response.json();',
            'var token = (json.data && json.data.token) ? json.data.token : json.token;',
            "if (token) { pm.collectionVariables.set('token', token); }",
        ],
    ],
]];

$merchantAuth = [
    'type' => 'apikey',
    'apikey' => [
        ['key' => 'key', 'value' => 'X-Merchant-Key', 'type' => 'string'],
        ['key' => 'value', 'value' => '{{merchant_api_key}}', 'type' => 'string'],
        ['key' => 'in', 'value' => 'header', 'type' => 'string'],
    ],
];

function req(string $name, string $method, string $path, array $options = []): array
{
    global $jsonHeaders, $acceptHeader, $noAuth;

    $hasBody = array_key_exists('body', $options);
    $formdata = $options['formdata'] ?? null;
    $headers = $options['headers'] ?? ($hasBody || $formdata ? $jsonHeaders : $acceptHeader);

    $url = '{{base_url}}/'.ltrim($path, '/');
    if (!empty($options['query'])) {
        $parts = [];
        foreach ($options['query'] as $key => $value) {
            $parts[] = $key.'='.$value;
        }
        $url .= '?'.implode('&', $parts);
    }

    $request = [
        'method' => strtoupper($method),
        'header' => $headers,
        'url' => $url,
    ];

    if (array_key_exists('auth', $options)) {
        $request['auth'] = $options['auth'];
    }

    if ($hasBody) {
        $request['body'] = [
            'mode' => 'raw',
            'raw' => json_encode($options['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    if ($formdata) {
        $request['header'] = $acceptHeader;
        $request['body'] = [
            'mode' => 'formdata',
            'formdata' => $formdata,
        ];
    }

    $item = [
        'name' => $name,
        'request' => $request,
    ];

    if (isset($options['event'])) {
        $item['event'] = $options['event'];
    }

    return $item;
}

function folder(string $name, array $items): array
{
    return ['name' => $name, 'item' => $items];
}

$pin = ['device_token' => '{{device_token}}', 'pin' => '1234'];

$collection = [
    'info' => [
        '_postman_id' => '8f1a2b3c-4d5e-4678-9abc-def012345678',
        'name' => 'Yoop API',
        'description' => 'API REST Yoop. Corre Auth > Login para gravar o token.',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [
            ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
        ],
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => $baseUrl, 'type' => 'string'],
        ['key' => 'token', 'value' => '', 'type' => 'string'],
        ['key' => 'merchant_api_key', 'value' => '', 'type' => 'string'],
        ['key' => 'device_token', 'value' => 'postman-device-1', 'type' => 'string'],
        ['key' => 'username', 'value' => 'johndoe', 'type' => 'string'],
        ['key' => 'card_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'funding_account_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'transaction_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'payment_intent_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'payment_method_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'merchant_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'news_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'notification_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'tpa_serial', 'value' => '', 'type' => 'string'],
    ],
    'item' => [
        folder('Auth', [
            req('Health', 'GET', 'auth/health', ['auth' => $noAuth]),
            req('Register Retail', 'POST', 'auth/register-retail', [
                'auth' => $noAuth,
                'event' => $saveToken,
                'body' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'username' => 'johndoe',
                    'password' => 'secret123',
                    'phone' => '351912345678',
                    'currency' => 'EUR',
                    'language' => 'pt',
                ],
            ]),
            req('Register Corporate', 'POST', 'auth/register-corporate', [
                'auth' => $noAuth,
                'event' => $saveToken,
                'body' => [
                    'name' => 'Acme Corp',
                    'username' => 'acmecorp',
                    'email' => 'admin@acme.com',
                    'tax_id' => 'PT123456789',
                    'password' => 'secret123',
                    'country_code' => 'PT',
                ],
            ]),
            req('Login', 'POST', 'auth/login', [
                'auth' => $noAuth,
                'event' => $saveToken,
                'body' => ['email' => 'john@example.com', 'password' => 'secret123'],
            ]),
            req('Login with PIN', 'POST', 'auth/login-pin', [
                'auth' => $noAuth,
                'event' => $saveToken,
                'body' => ['email' => 'john@example.com', 'device_token' => '{{device_token}}', 'pin' => '1234'],
            ]),
            req('Login via TPA', 'POST', 'auth/login-tpa', [
                'auth' => $noAuth,
                'event' => $saveToken,
                'body' => ['email' => 'john@example.com', 'password' => 'secret123', 'tpa_serial' => '{{tpa_serial}}'],
            ]),
            req('Logout', 'POST', 'auth/logout'),
            req('Forgot Password', 'POST', 'auth/forgot-password', [
                'auth' => $noAuth,
                'body' => ['email' => 'john@example.com'],
            ]),
            req('Reset Password', 'POST', 'auth/reset-password', [
                'auth' => $noAuth,
                'body' => ['email' => 'john@example.com', 'token' => 'RESET_TOKEN', 'password' => 'newsecret123'],
            ]),
        ]),
        folder('PIN', [
            req('Create PIN', 'POST', 'auth/create-pin', [
                'body' => ['device_token' => '{{device_token}}', 'device_name' => 'Postman', 'pin' => '1234'],
            ]),
            req('Reset PIN', 'POST', 'auth/reset-pin', [
                'body' => ['device_token' => '{{device_token}}', 'pin' => '4321', 'current_pin' => '1234'],
            ]),
        ]),
        folder('Onboarding', [
            req('Health', 'GET', 'onboarding/health', ['auth' => $noAuth]),
            req('Send Phone Code', 'POST', 'onboarding/phone/send'),
            req('Validate Phone Code', 'POST', 'onboarding/phone/validate', [
                'body' => ['code' => '123456'],
            ]),
        ]),
        folder('KYC', [
            req('Health', 'GET', 'kyc/health', ['auth' => $noAuth]),
            req('Create Identity Session', 'POST', 'kyc/identity/session'),
        ]),
        folder('TPA', [
            req('Health', 'GET', 'tpa/health', ['auth' => $noAuth]),
            req('Link TPA', 'POST', 'tpa/link', [
                'auth' => $noAuth,
                'body' => [
                    'email' => 'john@example.com',
                    'password' => 'secret123',
                    'tpa_serial' => '{{tpa_serial}}',
                ],
            ]),
        ]),
        folder('Customer', [
            req('Health', 'GET', 'customer/health', ['auth' => $noAuth]),
            req('Get My Profile', 'GET', 'customer/me'),
            req('Update Profile', 'POST', 'customer/update', [
                'body' => ['name' => 'John Doe', 'username' => '{{username}}', 'show_profile_pic' => true],
            ]),
            req('Upload Avatar', 'POST', 'customer/upload-avatar', [
                'formdata' => [
                    ['key' => 'avatar', 'type' => 'file', 'src' => ''],
                ],
            ]),
            req('Find by Username', 'GET', 'customer/find/{{username}}'),
            req('Favorites', 'GET', 'customer/favorites'),
            req('Add Favorite', 'POST', 'customer/favorites', [
                'body' => ['username' => '{{username}}'],
            ]),
            req('Remove Favorite', 'DELETE', 'customer/favorites/{{username}}'),
            req('Send Validation Email', 'POST', 'customer/send-validation-email'),
            req('Create Corporate Responsible', 'POST', 'customer/create-corporate-responsible', [
                'body' => ['name' => 'Maria Silva', 'username' => 'mariasilva', 'email' => 'maria@acme.com'],
            ]),
            req('Request Phone Change', 'POST', 'customer/phone', [
                'body' => ['phone' => '351912345678'],
            ]),
            req('Confirm Phone Change', 'POST', 'customer/phone/confirm', [
                'body' => ['phone' => '351912345678', 'code' => '123456'],
            ]),
        ]),
        folder('News', [
            req('List News', 'GET', 'customer/news'),
            req('Show News', 'GET', 'customer/news/{{news_id}}'),
        ]),
        folder('Notifications', [
            req('List Notifications', 'GET', 'customer/notifications'),
            req('Mark as Read', 'POST', 'customer/notifications/{{notification_id}}/read'),
            req('Mark All as Read', 'POST', 'customer/notifications/read-all'),
        ]),
        folder('Wallet', [
            req('Health', 'GET', 'wallet/health', ['auth' => $noAuth]),
            req('Dashboard', 'GET', 'wallet/dashboard'),
            req('Dashboard Summary', 'GET', 'wallet/dashboard/summary'),
            req('Get Account', 'GET', 'wallet/account'),
            req('Get Balance', 'GET', 'wallet/account/balance'),
            req('List Funding Account Movements', 'GET', 'wallet/funding-accounts/{{funding_account_id}}/movements', [
                'query' => ['per_page' => '15'],
            ]),
        ]),
        folder('Transactions', [
            req('List Transactions', 'GET', 'wallet/transactions', [
                'query' => ['per_page' => '15'],
            ]),
            req('Get Transaction', 'GET', 'wallet/transactions/{{transaction_id}}'),
            req('P2P Transfer', 'POST', 'wallet/transactions/p2p', [
                'headers' => array_merge($jsonHeaders, [
                    ['key' => 'Idempotency-Key', 'value' => '{{$guid}}', 'type' => 'text'],
                ]),
                'body' => [
                    'recipient_username' => '{{username}}',
                    'amount' => 100,
                    'currency' => 'EUR',
                    'description' => 'Transferencia entre contas',
                    'device_token' => '{{device_token}}',
                    'pin' => '1234',
                    'favorite' => false,
                ],
            ]),
        ]),
        folder('Stripe', [
            req('Health', 'GET', 'stripe/health', ['auth' => $noAuth]),
            req('List Payment Methods', 'GET', 'stripe/payment-methods', [
                'query' => ['currency' => 'EUR'],
            ]),
            req('Fee Estimates', 'GET', 'stripe/fee-estimates', [
                'query' => ['currency' => 'EUR'],
            ]),
            req('Create Payment Intent', 'POST', 'stripe/create-intent-payment', [
                'headers' => array_merge($jsonHeaders, [
                    ['key' => 'Idempotency-Key', 'value' => '{{$guid}}', 'type' => 'text'],
                ]),
                'body' => [
                    'amount' => 5000,
                    'payment_method_id' => '{{payment_method_id}}',
                    'currency' => 'EUR',
                ],
            ]),
            req('Show Payment Intent', 'GET', 'stripe/payment-intents/{{payment_intent_id}}'),
            req('Create Funding', 'POST', 'stripe/create-funding', [
                'body' => ['amount' => 5000, 'currency' => 'EUR'],
            ]),
            req('Get Funding Instructions', 'GET', 'stripe/get-funding-instructions'),
            req('Capture Terminal Payment', 'POST', 'stripe/terminal/{{payment_intent_id}}/capture'),
        ]),
        folder('Merchant', [
            req('Health', 'GET', 'merchant/health', ['auth' => $noAuth]),
            req('List My Merchants', 'GET', 'merchant'),
            req('Register Merchant', 'POST', 'merchant/register', [
                'body' => [
                    'name' => 'My Shop',
                    'mode' => 'test',
                    'webhook_url' => 'https://example.com/webhooks/yoop',
                    'allowed_currencies' => ['EUR'],
                ],
            ]),
            req('Update Merchant', 'PATCH', 'merchant/{{merchant_id}}', [
                'body' => ['name' => 'My Shop', 'mode' => 'test'],
            ]),
            req('Show Credentials', 'GET', 'merchant/{{merchant_id}}/credentials'),
            req('Rotate Credentials', 'POST', 'merchant/{{merchant_id}}/credentials/rotate'),
            req('List Payment Requests', 'GET', 'merchant/payment-requests'),
            req('Create Payment Request (customer)', 'POST', 'merchant/{{merchant_id}}/payment-requests', [
                'body' => [
                    'amount' => 2500,
                    'description' => 'Order #123',
                    'payment_method_types' => ['card', 'mb_way'],
                ],
            ]),
            req('Create Payment Request (partner)', 'POST', 'merchant/payment-requests', [
                'auth' => $merchantAuth,
                'body' => [
                    'amount' => 5000,
                    'description' => 'Order #123',
                    'external_reference' => 'ext-123',
                    'payment_method_types' => ['card', 'mb_way'],
                ],
            ]),
            req('Terminal Connection Token', 'POST', 'merchant/terminal/connection-token', [
                'auth' => $merchantAuth,
            ]),
            req('Create Terminal Payment Intent', 'POST', 'merchant/terminal/create-intent', [
                'auth' => $merchantAuth,
                'headers' => array_merge($jsonHeaders, [
                    ['key' => 'Idempotency-Key', 'value' => '{{$guid}}', 'type' => 'text'],
                    ['key' => 'X-Merchant-Key', 'value' => '{{merchant_api_key}}', 'type' => 'text'],
                ]),
                'body' => [
                    'tpa_serial_number' => '{{tpa_serial}}',
                    'amount' => 2000,
                    'currency' => 'EUR',
                ],
            ]),
        ]),
        folder('Settings', [
            req('Health', 'GET', 'settings/health', ['auth' => $noAuth]),
            req('System Info', 'GET', 'settings/system-info', ['auth' => $noAuth]),
            req('List Settings', 'GET', 'settings'),
            req('Get Setting', 'GET', 'settings/maintenance_mode'),
            req('Set Setting', 'POST', 'settings/maintenance_mode', [
                'body' => ['value' => 'false'],
            ]),
        ]),
        folder('Webhooks', [
            req('Didit', 'POST', 'webhooks/didit', [
                'auth' => $noAuth,
                'body' => ['session_id' => 'SESSION_ID', 'status' => 'Approved'],
            ]),
            req('Stripe', 'POST', 'webhooks/stripe', [
                'auth' => $noAuth,
                'headers' => array_merge($jsonHeaders, [
                    ['key' => 'Stripe-Signature', 'value' => 't=...,v1=...', 'type' => 'text'],
                ]),
                'body' => ['type' => 'payment_intent.succeeded', 'data' => new stdClass()],
            ]),
            req('Ebury', 'POST', 'webhooks/ebury', [
                'auth' => $noAuth,
                'body' => new stdClass(),
            ]),
        ]),
    ],
];

$root = dirname(__DIR__, 2);
$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
file_put_contents($root.'/yoop-api.postman_collection.json', $json);

$env = [
    'id' => '3b241101-e2bb-4255-8caf-4136c566a962',
    'name' => 'Yoop API (dashboard)',
    'values' => [
        ['key' => 'base_url', 'value' => $baseUrl, 'type' => 'default', 'enabled' => true],
        ['key' => 'token', 'value' => '', 'type' => 'default', 'enabled' => true],
        ['key' => 'merchant_api_key', 'value' => '', 'type' => 'secret', 'enabled' => true],
        ['key' => 'device_token', 'value' => 'postman-device-1', 'type' => 'default', 'enabled' => true],
        ['key' => 'username', 'value' => 'johndoe', 'type' => 'default', 'enabled' => true],
        ['key' => 'tpa_serial', 'value' => '', 'type' => 'default', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
    '_postman_exported_using' => 'Postman',
];
file_put_contents(
    $root.'/postman/Yoop API.postman_environment.json',
    json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

echo "Wrote yoop-api.postman_collection.json\n";
echo "Wrote postman/Yoop API.postman_environment.json\n";
