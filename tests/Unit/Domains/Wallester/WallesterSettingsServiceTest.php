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

    public function test_reads_global_and_per_currency_account_settings(): void
    {
        $service = app(WallesterSettingsService::class);

        $this->assertSame('wallester-api-key', $service->apiKey());
        $this->assertSame('WALLESTER-PRODUCT', $service->productCode());
        $this->assertSame('75d92dc4-6de9-4580-8b62-3cbd3513af56', $service->platformMasterAccountId('EUR'));
        $this->assertSame('https://api-frontend.wallester.com/v1/', $service->baseUrl());
        $this->assertSame('Backend', $service->auditSourceType());
        $this->assertSame(300, $service->threeDsChallengeTtlSeconds());
        $this->assertSame(0, $service->cardValueCents(true));
        $this->assertSame(0, $service->cardValueCents(false));
        $this->assertTrue($service->hasCredentials());
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

    public function test_has_credentials_without_product_code(): void
    {
        Setting::query()->where('key', 'wallester.product_code')->update(['value' => '']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame('', $service->productCode());
        $this->assertTrue($service->hasCredentials());
    }

    public function test_card_rsa_private_key_falls_back_to_private_key(): void
    {
        Setting::query()->where('key', 'wallester.card_rsa_private_key')->update(['value' => '']);

        $service = app(WallesterSettingsService::class);

        $this->assertSame(
            str_replace('\\n', "\n", trim(Setting::query()->where('key', 'wallester.private_key')->value('value'))),
            $service->cardRsaPrivateKey('EUR'),
        );
    }

    public function test_rsa_public_key_is_read_from_settings(): void
    {
        Setting::query()->where('key', 'wallester.rsa_public_key')->update([
            'value' => '-----BEGIN PUBLIC KEY-----\\nMIIB\\n-----END PUBLIC KEY-----',
        ]);

        $service = app(WallesterSettingsService::class);

        $this->assertSame(
            "-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----",
            $service->rsaPublicKey(),
        );
    }

    public function test_throws_when_rsa_public_key_is_the_yoop_signing_key(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        $privateKeyPem = '';
        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);
        $this->assertIsString($details['key'] ?? null);

        Setting::query()->where('key', 'wallester.private_key')->update(['value' => $privateKeyPem]);
        Setting::query()->where('key', 'wallester.rsa_public_key')->update(['value' => $details['key']]);

        $service = app(WallesterSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester RSA public key matches the Yoop signing key.');

        $service->rsaPublicKey();
    }

    public function test_throws_when_rsa_public_key_is_missing(): void
    {
        Setting::query()->where('key', 'wallester.rsa_public_key')->update(['value' => '']);

        $service = app(WallesterSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester RSA public key is not configured.');

        $service->rsaPublicKey();
    }

    public function test_rsa_public_key_accepts_base64_der(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);
        $this->assertIsString($details['key'] ?? null);

        $der = base64_decode((string) preg_replace(
            '/-----[^-]+-----|\s+/',
            '',
            $details['key'],
        ), true);
        $this->assertNotFalse($der);

        Setting::query()->where('key', 'wallester.rsa_public_key')->update([
            'value' => base64_encode($der),
        ]);

        $pem = app(WallesterSettingsService::class)->rsaPublicKey();
        $parsed = openssl_pkey_get_public($pem);

        $this->assertNotFalse($parsed);
        $this->assertSame(
            openssl_pkey_get_details($parsed)['bits'] ?? null,
            $details['bits'] ?? null,
        );
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        Setting::query()->where('key', 'wallester.api_key')->delete();

        $service = app(WallesterSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('wallester.api_key');

        $service->apiKey();
    }

    public function test_three_ds_webhook_credentials_are_read_from_settings(): void
    {
        $service = app(WallesterSettingsService::class);

        $this->assertSame('test-user', $service->threeDsWebhookUsername());
        $this->assertSame('test-pass', $service->threeDsWebhookPassword());
    }
}
