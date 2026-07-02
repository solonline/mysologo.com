#!/usr/bin/env php
<?php

use App\Domains\Wallester\Clients\WallesterHttpClient;
use Illuminate\Contracts\Console\Kernel;

/**
 * Gera postman/wallester-sandbox.local.postman_environment.json a partir do .env Laravel.
 * Inclui JWT já assinado — importa este ficheiro no Postman (não committar).
 *
 * Uso: php postman/scripts/sync-postman-env.php
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$baseUrl = rtrim((string) config('services.wallester.base_url', 'https://api-frontend.wallester.com/v1/'), '/');
$apiKey = (string) config('services.wallester.api_key');
$privateKey = (string) config('services.wallester.private_key');
$productCode = (string) config('services.wallester.product_code');
$auditSourceType = (string) config('services.wallester.audit_source_type', 'Backend');

if ($apiKey === '' || $privateKey === '') {
    fwrite(STDERR, "ERRO: WALLESTER_API_KEY e WALLESTER_PRIVATE_KEY devem estar definidos no .env\n");
    exit(1);
}

if ($productCode === '') {
    fwrite(STDERR, "ERRO: WALLESTER_PRODUCT_CODE esta vazio no .env\n");
    fwrite(STDERR, "A Wallester exige o header X-Product-Code em todos os pedidos (sem isto = 401).\n");
    fwrite(STDERR, "Pede o product code ao suporte Wallester e adiciona ao .env:\n");
    fwrite(STDERR, "  WALLESTER_PRODUCT_CODE=teu_codigo\n");
    exit(1);
}

$jwt = app(WallesterHttpClient::class)->authenticate();

$environment = [
    'id' => 'c4d5e6f7-a8b9-4c1d-9e0f-1a2b3c4d5e6f',
    'name' => 'Wallester Sandbox (local)',
    'values' => [
        ['key' => 'wallester_base_url', 'value' => $baseUrl, 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_api_key', 'value' => $apiKey, 'type' => 'secret', 'enabled' => true],
        ['key' => 'wallester_private_key', 'value' => str_replace("\n", '\\n', $privateKey), 'type' => 'secret', 'enabled' => true],
        ['key' => 'wallester_product_code', 'value' => $productCode, 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_audit_source_type', 'value' => $auditSourceType, 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_audit_user_id', 'value' => 'postman-test-user', 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_jwt', 'value' => $jwt, 'type' => 'secret', 'enabled' => true],
        ['key' => 'wallester_account_id', 'value' => '', 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_card_id', 'value' => '', 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_transaction_id', 'value' => '', 'type' => 'default', 'enabled' => true],
        ['key' => 'wallester_oob_id', 'value' => '00000000-0000-4000-8000-000000000001', 'type' => 'default', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
    '_postman_exported_at' => gmdate('Y-m-d\TH:i:s.v\Z'),
    '_postman_exported_using' => 'Yoop sync-postman-env',
];

$outputPath = __DIR__.'/../wallester-sandbox.local.postman_environment.json';

file_put_contents(
    $outputPath,
    json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

echo "OK: {$outputPath}\n";
echo "Importa no Postman: Wallester Sandbox (local)\n";
echo "JWT valido neste momento — se receberes 401, volta a correr este script.\n";
