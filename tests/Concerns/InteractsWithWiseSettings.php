<?php

namespace Tests\Concerns;

use App\Models\Setting;

trait InteractsWithWiseSettings
{
    protected function seedWiseSettings(array $overrides = []): void
    {
        $settings = [
            ['key' => 'wise.base_url', 'label' => 'Base URL', 'type' => 'url', 'value' => 'https://api.wise-sandbox.com'],
            ['key' => 'wise.api_token', 'label' => 'API Token', 'type' => 'secret', 'value' => 'test-wise-token'],
            ['key' => 'wise.profile_id', 'label' => 'Profile ID', 'type' => 'text', 'value' => '12345'],
            ['key' => 'wise.webhook_public_key', 'label' => 'Webhook Public Key (PEM)', 'type' => 'textarea', 'value' => ''],
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
}
