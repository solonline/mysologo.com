<?php

namespace App\Domains\Auth\DTOs;

class CreatePinDTO
{
    public function __construct(
        public readonly string $deviceToken,
        public readonly string $deviceName,
        public readonly string $pin,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            deviceToken: $data['device_token'],
            deviceName: $data['device_name'],
            pin: $data['pin'],
        );
    }
}
