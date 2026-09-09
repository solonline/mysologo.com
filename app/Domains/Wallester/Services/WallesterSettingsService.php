<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Settings\Services\SettingsService;
use RuntimeException;

class WallesterSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function apiKey(string $currency): string
    {
        return $this->requiredCurrencySetting($currency, 'api_key');
    }

    public function privateKey(string $currency): string
    {
        return str_replace('\\n', "\n", trim($this->requiredCurrencySetting($currency, 'private_key')));
    }

    public function productCode(string $currency): string
    {
        $key = 'wallester.'.strtolower($currency).'.product_code';
        $value = $this->settings->getByKey($key);

        return $value === null ? '' : trim((string) $value);
    }

    public function platformMasterAccountId(string $currency): string
    {
        return $this->requiredCurrencySetting($currency, 'platform_master_account_id');
    }

    public function platformFeeAccountId(string $currency): string
    {
        $key = 'wallester.'.strtolower($currency).'.platform_fee_account_id';
        $value = $this->settings->getByKey($key);

        return $value === null ? '' : trim((string) $value);
    }

    public function baseUrl(): string
    {
        return rtrim($this->requiredGlobalSetting('base_url'), '/').'/';
    }

    public function auditSourceType(): string
    {
        $value = $this->settings->getByKey('wallester.audit_source_type');

        if ($value === null || $value === '') {
            return 'Backend';
        }

        return $value;
    }

    public function cardRsaPrivateKey(string $currency = 'eur'): string
    {
        $value = $this->settings->getByKey('wallester.card_rsa_private_key');

        if ($value !== null && trim($value) !== '') {
            return str_replace('\\n', "\n", trim($value));
        }

        return $this->privateKey($currency);
    }

    public function threeDsChallengeTtlSeconds(): int
    {
        $value = $this->settings->getByKey('wallester.3ds_challenge_ttl_seconds');

        if ($value === null || $value === '') {
            return 300;
        }

        return (int) $value;
    }

    public function cardValueCents(bool $isVirtual): int
    {
        $value = $this->settings->getByKey($isVirtual ? 'wallester.virtual_card_value' : 'wallester.physical_card_value');

        if ($value === null || trim((string) $value) === '') {
            return 0;
        }

        return max(0, (int) round((float) $value));
    }

    public function threeDsWebhookUsername(): string
    {
        return (string) ($this->settings->getByKey('wallester.3ds_webhook_username') ?? '');
    }

    public function threeDsWebhookPassword(): string
    {
        return (string) ($this->settings->getByKey('wallester.3ds_webhook_password') ?? '');
    }

    public function hasCurrencyCredentials(string $currency): bool
    {
        $currency = strtolower($currency);

        foreach (['api_key', 'private_key'] as $suffix) {
            $value = $this->settings->getByKey("wallester.{$currency}.{$suffix}");

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    private function requiredCurrencySetting(string $currency, string $suffix): string
    {
        $key = 'wallester.'.strtolower($currency).'.'.$suffix;
        $value = $this->settings->getByKey($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Wallester setting [{$key}] is not configured.");
        }

        return $value;
    }

    private function requiredGlobalSetting(string $suffix): string
    {
        $key = 'wallester.'.$suffix;
        $value = $this->settings->getByKey($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Wallester setting [{$key}] is not configured.");
        }

        return $value;
    }
}
