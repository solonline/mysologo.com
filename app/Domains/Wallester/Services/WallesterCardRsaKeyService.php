<?php

namespace App\Domains\Wallester\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use RuntimeException;

class WallesterCardRsaKeyService
{
    public function __construct(
        private readonly WallesterSettingsService $wallesterSettings,
    ) {}

    public function publicKeyBase64(string $currency = 'eur'): string
    {
        $publicKeyPem = $this->publicKeyPem($currency);

        return base64_encode($publicKeyPem);
    }

    public function privateKeyPem(string $currency = 'eur'): string
    {
        try {
            return $this->wallesterSettings->cardRsaPrivateKey($currency);
        } catch (RuntimeException $exception) {
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Wallester card RSA private key is not configured.',
                previous: $exception,
            );
        }
    }

    private function publicKeyPem(string $currency = 'eur'): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem($currency));

        if ($privateKey === false) {
            throw new RuntimeException('Invalid Wallester card RSA private key.');
        }

        $details = openssl_pkey_get_details($privateKey);

        if ($details === false || ! is_string($details['key'] ?? null)) {
            throw new RuntimeException('Unable to derive Wallester card RSA public key.');
        }

        return $details['key'];
    }
}
