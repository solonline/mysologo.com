<?php

namespace App\DTOs;

class LoginWithPinDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $deviceToken,
        public readonly string $pin,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            deviceToken: $data['device_token'],
            pin: $data['pin'],
        );
    }
}
