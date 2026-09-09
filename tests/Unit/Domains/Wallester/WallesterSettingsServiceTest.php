<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterSettingsServiceTest extends TestCase
{
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallesterSettings();
    }

    public function test_reads_per_currency_and_global_settings(): void
    {
        $service = app(WallesterSettingsService::class);

        $this->assertSame('eur-api-key', $service->apiKey('EUR'));
        $this->assertSame('usd-api-key', $service->apiKey('usd'));
        $this->assertSame('EUR-PRODUCT', $service->productCode('EUR'));
        $this->assertSame('75d92dc4-6de9-4580-8b62-3cbd3513af56', $service->platformMasterAccountId('EUR'));
        $this->assertSame('https://api-frontend.wallester.com/v1/', $service->baseUrl());
        $this->assertSame('Backend', $service->auditSourceType());
        $this->assertSame(300, $service->threeDsChallengeTtlSeconds());
        $this->assertSame(0, $service->cardValueCents(true));
        $this->assertSame(0, $service->cardValueCents(false));
        $this->assertTrue($service->hasCurrencyCredentials('EUR'));
    }

    public function test_virtual_card_value_is_read_as_cents(): void
    {
        Setting::query()->where('key', 'wallester.virtual_card_value')->update(['value' => '45']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame(45, $service->cardValueCents(true));
        $this->assertSame(0, $service->cardValueCents(false));
    }

    public function test_physical_card_value_is_read_as_cents(): void
    {
        Setting::query()->where('key', 'wallester.physical_card_value')->update(['value' => '1500']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame(1500, $service->cardValueCents(false));
        $this->assertSame(0, $service->cardValueCents(true));
    }

    public function test_has_currency_credentials_without_product_code(): void
    {
        Setting::query()->where('key', 'wallester.eur.product_code')->update(['value' => '']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame('', $service->productCode('EUR'));
        $this->assertTrue($service->hasCurrencyCredentials('EUR'));
    }

    public function test_card_rsa_private_key_falls_back_to_currency_private_key(): void
    {
        Setting::query()->where('key', 'wallester.card_rsa_private_key')->update(['value' => '']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame(
            str_replace('\\n', "\n", trim(Setting::query()->where('key', 'wallester.eur.private_key')->value('value'))),
            $service->cardRsaPrivateKey('EUR'),
        );
    }

    public function test_throws_when_currency_setting_missing(): void
    {
        Setting::query()->where('key', 'wallester.usd.api_key')->delete();

        $service = app(WallesterSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('wallester.usd.api_key');

        $service->apiKey('USD');
    }

    public function test_three_ds_webhook_credentials_are_read_from_settings(): void
    {
        $service = app(WallesterSettingsService::class);

        $this->assertSame('test-user', $service->threeDsWebhookUsername());
        $this->assertSame('test-pass', $service->threeDsWebhookPassword());
    }
}
