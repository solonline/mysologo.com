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
            ['key' => 'wallester.rsa_public_key', 'label' => 'Wallester RSA Public Key', 'type' => 'textarea', 'value' => ''],
            ['key' => 'wallester.3ds_challenge_ttl_seconds', 'label' => '3DS TTL', 'type' => 'number', 'value' => '300'],
            ['key' => 'wallester.3ds_webhook_username', 'label' => '3DS Username', 'type' => 'secret', 'value' => 'test-user'],
            ['key' => 'wallester.3ds_webhook_password', 'label' => '3DS Password', 'type' => 'secret', 'value' => 'test-pass'],
            ['key' => 'wallester.virtual_card_value', 'label' => 'Taxa do cartão virtual adicional (cêntimos)', 'type' => 'number', 'value' => '0'],
            ['key' => 'wallester.physical_card_value', 'label' => 'Taxa do cartão físico adicional (cêntimos)', 'type' => 'number', 'value' => '0'],
            ['key' => 'wallester.api_key', 'label' => 'API Key', 'type' => 'secret', 'value' => 'wallester-api-key'],
            ['key' => 'wallester.private_key', 'label' => 'Private Key', 'type' => 'textarea', 'value' => "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAK5...\n-----END RSA PRIVATE KEY-----"],
            ['key' => 'wallester.product_code', 'label' => 'Product Code', 'type' => 'text', 'value' => 'WALLESTER-PRODUCT'],
            ['key' => 'wallester.eur.platform_master_account_id', 'label' => 'Platform Master Account ID (EUR)', 'type' => 'text', 'value' => '75d92dc4-6de9-4580-8b62-3cbd3513af56'],
            ['key' => 'wallester.eur.platform_fee_account_id', 'label' => 'Sub Conta Master (lucro) (EUR)', 'type' => 'text', 'value' => ''],
            ['key' => 'wallester.usd.platform_master_account_id', 'label' => 'Platform Master Account ID (USD)', 'type' => 'text', 'value' => 'configured-usd-uuid'],
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
        $gateway->shouldReceive('getAccount')->andReturn([
            'available_amount' => 0,
            'balance' => 0,
            'blocked_amount' => 0,
        ])->byDefault();
        $gateway->shouldReceive('listCardDispatchMethods')->andReturn([
            [
                'dispatch_method' => 'DHLGlobalMail',
                'price' => 5.0,
                'description' => 'Standard',
            ],
        ])->byDefault();

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    protected function expectWallesterGetCard(
        MockInterface $gateway,
        string $cardId,
        string $status = 'Created',
        array $card = [],
    ): void {
        $gateway->shouldReceive('getCard')
            ->with($cardId, Mockery::any())
            ->andReturn(array_merge([
                'id' => $cardId,
                'status' => $status,
            ], $card));
    }

    /**
     * @param  array<string, float>  $masterBalance
     * @param  array<string, float>  $toBalance
     */
    protected function expectWallesterBalanceSyncAfterMasterTransfer(
        MockInterface&WallesterGatewayInterface $gateway,
        string $masterAccountId,
        string $toAccountId,
        int|string $toCustomerId,
        array $masterBalance = ['balance' => 1000.00, 'available_amount' => 950.00],
        array $toBalance = ['balance' => 50.00, 'available_amount' => 50.00],
    ): void {
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with($masterAccountId, 'platform')
            ->andReturn($masterBalance);

        $gateway->shouldReceive('getAccount')
            ->once()
            ->with($toAccountId, (string) $toCustomerId)
            ->andReturn($toBalance);
    }
}
