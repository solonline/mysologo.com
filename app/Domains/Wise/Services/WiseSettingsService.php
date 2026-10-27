<?php

namespace App\Domains\Wise\Services;

use App\Domains\Settings\Services\SettingsService;
use RuntimeException;

class WiseSettingsService
{
    public const SANDBOX_WEBHOOK_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwpb91cEYuyJNQepZAVfP
ZIlPZfNUefH+n6w9SW3fykqKu938cR7WadQv87oF2VuT+fDt7kqeRziTmPSUhqPU
ys/V2Q1rlfJuXbE+Gga37t7zwd0egQ+KyOEHQOpcTwKmtZ81ieGHynAQzsn1We3j
wt760MsCPJ7GMT141ByQM+yW1Bx+4SG3IGjXWyqOWrcXsxAvIXkpUD/jK/L958Cg
nZEgz0BSEh0QxYLITnW1lLokSx/dTianWPFEhMC9BgijempgNXHNfcVirg1lPSyg
z7KqoKUN0oHqWLr2U1A+7kqrl6O2nx3CKs1bj1hToT1+p4kcMoHXA7kA+VBLUpEs
VwIDAQAB
-----END PUBLIC KEY-----
PEM;

    public const PRODUCTION_WEBHOOK_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvO8vXV+JksBzZAY6GhSO
XdoTCfhXaaiZ+qAbtaDBiu2AGkGVpmEygFmWP4Li9m5+Ni85BhVvZOodM9epgW3F
bA5Q1SexvAF1PPjX4JpMstak/QhAgl1qMSqEevL8cmUeTgcMuVWCJmlge9h7B1CS
D4rtlimGZozG39rUBDg6Qt2K+P4wBfLblL0k4C4YUdLnpGYEDIth+i8XsRpFlogx
CAFyH9+knYsDbR43UJ9shtc42Ybd40Afihj8KnYKXzchyQ42aC8aZ/h5hyZ28yVy
Oj3Vos0VdBIs/gAyJ/4yyQFCXYte64I7ssrlbGRaco4nKF3HmaNhxwyKyJafz19e
HwIDAQAB
-----END PUBLIC KEY-----
PEM;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function baseUrl(): string
    {
        return rtrim($this->required('base_url'), '/').'/';
    }

    public function apiToken(): string
    {
        return $this->required('api_token');
    }

    public function profileId(): string
    {
        return $this->required('profile_id');
    }

    public function scaPrivateKey(): string
    {
        $path = storage_path('app/private/wise/sca-private.pem');
        $key = is_file($path) ? file_get_contents($path) : false;

        if ($key === false || trim($key) === '') {
            throw new RuntimeException('Wise SCA private key is not configured.');
        }

        return $key;
    }

    public function webhookPublicKey(): string
    {
        $value = $this->settings->getByKey('wise.webhook_public_key');

        if ($value === null || trim($value) === '') {
            throw new RuntimeException('Wise webhook public key is not configured.');
        }

        return $this->normalizeRsaPublicKey(str_replace('\\n', "\n", trim($value)));
    }

    public function hasCredentials(): bool
    {
        foreach (['api_token', 'profile_id'] as $suffix) {
            $value = $this->settings->getByKey("wise.{$suffix}");

            if ($value === null || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    public function hasWebhookPublicKey(): bool
    {
        $value = $this->settings->getByKey('wise.webhook_public_key');

        return $value !== null && trim($value) !== '';
    }

    /**
     * @return list<string>
     */
    public function webhookVerificationKeys(): array
    {
        $keys = [];

        if ($this->hasWebhookPublicKey()) {
            $keys[] = $this->webhookPublicKey();
        }

        $baseUrl = strtolower(trim((string) $this->settings->getByKey('wise.base_url')));
        $preferred = $baseUrl !== '' && ! str_contains($baseUrl, 'sandbox')
            ? self::PRODUCTION_WEBHOOK_PUBLIC_KEY
            : self::SANDBOX_WEBHOOK_PUBLIC_KEY;
        $fallback = $preferred === self::SANDBOX_WEBHOOK_PUBLIC_KEY
            ? self::PRODUCTION_WEBHOOK_PUBLIC_KEY
            : self::SANDBOX_WEBHOOK_PUBLIC_KEY;

        foreach ([$preferred, $fallback] as $official) {
            if (! in_array($official, $keys, true)) {
                $keys[] = $official;
            }
        }

        return $keys;
    }

    private function required(string $suffix): string
    {
        $key = 'wise.'.$suffix;
        $value = $this->settings->getByKey($key);

        if ($value === null || trim($value) === '') {
            throw new RuntimeException("Wise setting [{$key}] is not configured.");
        }

        return trim($value);
    }

    private function normalizeRsaPublicKey(string $value): string
    {
        if (
            str_contains($value, 'BEGIN PUBLIC KEY')
            || str_contains($value, 'BEGIN RSA PUBLIC KEY')
            || str_contains($value, 'BEGIN CERTIFICATE')
        ) {
            return $value;
        }

        $compact = preg_replace('/\s+/', '', $value) ?? $value;
        $decoded = base64_decode($compact, true);

        if (! is_string($decoded) || $decoded === '') {
            return $value;
        }

        if (
            str_contains($decoded, 'BEGIN PUBLIC KEY')
            || str_contains($decoded, 'BEGIN RSA PUBLIC KEY')
        ) {
            return $decoded;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($decoded), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem) === false ? $value : $pem;
    }
}
