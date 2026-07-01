<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Settings\Services\SettingsService;

class WallesterSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function threeDsWebhookUsername(): string
    {
        return (string) ($this->settings->getByKey('wallester.3ds_webhook_username') ?? '');
    }

    public function threeDsWebhookPassword(): string
    {
        return (string) ($this->settings->getByKey('wallester.3ds_webhook_password') ?? '');
    }
}
