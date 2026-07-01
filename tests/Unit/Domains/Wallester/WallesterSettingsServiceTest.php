<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallesterSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_ds_webhook_credentials_are_read_from_settings(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'wallester.3ds_webhook_username'],
            ['value' => 'settings-user', 'type' => 'secret', 'label' => 'Username'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'wallester.3ds_webhook_password'],
            ['value' => 'settings-pass', 'type' => 'secret', 'label' => 'Password'],
        );

        $service = app(WallesterSettingsService::class);

        $this->assertSame('settings-user', $service->threeDsWebhookUsername());
        $this->assertSame('settings-pass', $service->threeDsWebhookPassword());
    }
}
