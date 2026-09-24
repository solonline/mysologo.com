<?php

namespace App\Domains\Whatsapp\Services;

use App\Domains\Settings\Services\SettingsService;

class WhatsappSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function baseUrl(): string
    {
        return rtrim($this->resolve('whatsapp.base_url', 'services.whatsapp.base_url'), '/');
    }

    public function apiKey(): string
    {
        return $this->resolve('whatsapp.api_key', 'services.whatsapp.api_key');
    }

    public function instance(): string
    {
        return $this->resolve('whatsapp.instance', 'services.whatsapp.instance');
    }

    public function timeoutSeconds(): int
    {
        $value = $this->settings->getByKey('whatsapp.timeout_seconds');

        if ($value !== null && $value !== '') {
            $timeout = (int) $value;

            return $timeout > 0 ? $timeout : 15;
        }

        $timeout = (int) config('services.whatsapp.timeout_seconds', 15);

        return $timeout > 0 ? $timeout : 15;
    }

    public function hasCredentials(): bool
    {
        return $this->baseUrl() !== ''
            && $this->apiKey() !== ''
            && $this->instance() !== '';
    }

    private function resolve(string $settingKey, string $configKey): string
    {
        $value = $this->settings->getByKey($settingKey);

        if ($value !== null && trim($value) !== '') {
            return trim($value);
        }

        return trim((string) config($configKey, ''));
    }
}
