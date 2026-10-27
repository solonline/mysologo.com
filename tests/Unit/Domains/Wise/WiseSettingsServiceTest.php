<?php

namespace Tests\Unit\Domains\Wise;

use App\Domains\Wise\Services\WiseSettingsService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithWiseSettings;
use Tests\TestCase;

class WiseSettingsServiceTest extends TestCase
{
    use InteractsWithWiseSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWiseSettings();
    }

    public function test_reads_credentials_and_base_url(): void
    {
        $service = app(WiseSettingsService::class);

        $this->assertSame('https://api.wise-sandbox.com/', $service->baseUrl());
        $this->assertSame('test-wise-token', $service->apiToken());
        $this->assertSame('12345', $service->profileId());
        $this->assertTrue($service->hasCredentials());
        $this->assertFalse($service->hasWebhookPublicKey());
    }

    public function test_has_credentials_requires_token_and_profile(): void
    {
        Setting::query()->where('key', 'wise.api_token')->update(['value' => '']);

        $service = app(WiseSettingsService::class);

        $this->assertFalse($service->hasCredentials());

        Setting::query()->where('key', 'wise.api_token')->update(['value' => 'test-wise-token']);
        Setting::query()->where('key', 'wise.profile_id')->update(['value' => '']);

        $this->assertFalse($service->hasCredentials());
    }

    public function test_webhook_public_key_unescapes_newlines(): void
    {
        Setting::query()->where('key', 'wise.webhook_public_key')->update([
            'value' => '-----BEGIN PUBLIC KEY-----\\nMIIB\\n-----END PUBLIC KEY-----',
        ]);

        $service = app(WiseSettingsService::class);

        $this->assertSame(
            "-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----",
            $service->webhookPublicKey(),
        );
        $this->assertTrue($service->hasWebhookPublicKey());
    }

    public function test_throws_when_webhook_public_key_is_missing(): void
    {
        $service = app(WiseSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wise webhook public key is not configured.');

        $service->webhookPublicKey();
    }

    public function test_throws_when_base_url_is_missing(): void
    {
        Setting::query()->where('key', 'wise.base_url')->update(['value' => '']);

        $service = app(WiseSettingsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wise setting [wise.base_url] is not configured.');

        $service->baseUrl();
    }
}
