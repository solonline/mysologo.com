<?php

namespace Tests\Concerns;

use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\Setting;
use Mockery;
use Mockery\MockInterface;

trait InteractsWithWallesterSettings
{
    protected function seedWallesterSettings(array $overrides = []): void
    {
        $settings = [
            ['key' => 'wallester.base_url', 'label' => 'Base URL', 'type' => 'url', 'value' => 'https://api-frontend.wallester.com/v1/'],
            ['key' => 'wallester.audit_source_type', 'label' => 'Audit Source Type', 'type' => 'text', 'value' => 'Backend'],
            ['key' => 'wallester.card_rsa_private_key', 'label' => 'Card RSA Private Key', 'type' => 'textarea', 'value' => ''],
            ['key' => 'wallester.3ds_challenge_ttl_seconds', 'label' => '3DS TTL', 'type' => 'number', 'value' => '300'],
            ['key' => 'wallester.3ds_webhook_username', 'label' => '3DS Username', 'type' => 'secret', 'value' => 'test-user'],
            ['key' => 'wallester.3ds_webhook_password', 'label' => '3DS Password', 'type' => 'secret', 'value' => 'test-pass'],
            ['key' => 'wallester.eur.api_key', 'label' => 'EUR API Key', 'type' => 'secret', 'value' => 'eur-api-key'],
            ['key' => 'wallester.eur.private_key', 'label' => 'EUR Private Key', 'type' => 'textarea', 'value' => "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAK5...\n-----END RSA PRIVATE KEY-----"],
            ['key' => 'wallester.eur.product_code', 'label' => 'EUR Product Code', 'type' => 'text', 'value' => 'EUR-PRODUCT'],
            ['key' => 'wallester.eur.platform_master_account_id', 'label' => 'Platform Master Account ID', 'type' => 'text', 'value' => '75d92dc4-6de9-4580-8b62-3cbd3513af56'],
            ['key' => 'wallester.usd.api_key', 'label' => 'USD API Key', 'type' => 'secret', 'value' => 'usd-api-key'],
            ['key' => 'wallester.usd.private_key', 'label' => 'USD Private Key', 'type' => 'textarea', 'value' => "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAK5...\n-----END RSA PRIVATE KEY-----"],
            ['key' => 'wallester.usd.product_code', 'label' => 'USD Product Code', 'type' => 'text', 'value' => 'USD-PRODUCT'],
            ['key' => 'wallester.usd.platform_master_account_id', 'label' => 'USD Master', 'type' => 'text', 'value' => 'configured-usd-uuid'],
        ];

        foreach ($settings as $setting) {
            if (array_key_exists($setting['key'], $overrides)) {
                $setting['value'] = $overrides[$setting['key']];
            }

            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                ],
            );
        }
    }

    protected function mockWallesterGateway(): MockInterface&WallesterGatewayInterface
    {
        $gateway = Mockery::mock(WallesterGatewayInterface::class);
        $gateway->shouldReceive('forCurrency')->andReturnSelf()->byDefault();

        return $gateway;
    }
}
