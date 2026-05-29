<?php

namespace App\DTOs;

class LoginWithTpaDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $tpaSerial,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            tpaSerial: $data['tpa_serial'],
        );
    }
}
