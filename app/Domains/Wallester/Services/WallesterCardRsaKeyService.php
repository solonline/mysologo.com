<?php

namespace App\Domains\Wallester\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use RuntimeException;

class WallesterCardRsaKeyService
{
    public function publicKeyBase64(): string
    {
        $publicKeyPem = $this->publicKeyPem();

        return base64_encode($publicKeyPem);
    }

    public function privateKeyPem(): string
    {
        $privateKey = config('services.wallester.card_rsa_private_key');

        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Wallester card RSA private key is not configured.',
            );
        }

        return str_replace('\\n', "\n", trim($privateKey));
    }

    private function publicKeyPem(): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem());

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
