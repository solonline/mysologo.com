<?php

namespace App\Domains\Wallester\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use RuntimeException;

class WallesterSensitiveDataEncryptor
{
    public function __construct(
        private readonly WallesterSettingsService $settings,
    ) {}

    public function encryptCvv2(string $cvv): string
    {
        try {
            $ciphertext = WallesterRsaOaep::encrypt(
                $cvv,
                $this->settings->rsaPublicKey(),
                '',
            );
        } catch (RuntimeException $exception) {
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Unable to encrypt Wallester card CVV.',
                previous: $exception,
            );
        }

        $pem = "-----BEGIN MESSAGE-----\n"
            .chunk_split(base64_encode($ciphertext), 64, "\n")
            ."-----END MESSAGE-----\n";

        return base64_encode($pem);
    }
}
