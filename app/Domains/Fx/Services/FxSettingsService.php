<?php

namespace App\Domains\Fx\Services;

use App\Domains\Settings\Services\SettingsService;

class FxSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function baseUrl(): string
    {
        $value = $this->resolve('fx.base_url', 'services.fx.base_url');

        if ($value === '') {
            $value = 'https://economia.awesomeapi.com.br';
        }

        return rtrim($value, '/');
    }

    public function apiKey(): string
    {
        return $this->resolve('fx.api_key', 'services.fx.api_key');
    }

    public function cacheTtlSeconds(): int
    {
        $value = $this->settings->getByKey('fx.cache_ttl_seconds');

        if ($value !== null && $value !== '') {
            $ttl = (int) $value;

            return $ttl > 0 ? $ttl : 60;
        }

        $ttl = (int) config('services.fx.cache_ttl_seconds', 60);

        return $ttl > 0 ? $ttl : 60;
    }

    /**
     * Percentagem da taxa Yoop em compras com câmbio Wallester.
     * Estilo Stripe: 0.01 = 1%. Vazio ou 0 = não cobra.
     */
    public function percentage(): float
    {
        $value = $this->resolve('fx.percentage', 'services.fx.percentage');

        if ($value === '') {
            return 0.0;
        }

        $percentage = (float) $value;

        return $percentage > 0 ? $percentage : 0.0;
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
