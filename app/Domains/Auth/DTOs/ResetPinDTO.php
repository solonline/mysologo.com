<?php

namespace App\Domains\Auth\DTOs;

class ResetPinDTO
{
    public function __construct(
        public readonly string $deviceToken,
        public readonly string $pin,
        public readonly ?string $currentPin = null,
        public readonly ?string $password = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceToken: (string) $data['device_token'],
            pin: (string) $data['pin'],
            currentPin: isset($data['current_pin']) ? (string) $data['current_pin'] : null,
            password: isset($data['password']) ? (string) $data['password'] : null,
        );
    }
}
