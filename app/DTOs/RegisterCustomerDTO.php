<?php

namespace App\DTOs;

class RegisterCustomerDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $type,
        public readonly ?string $username = null,
        public readonly ?string $name = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            type: $data['type'],
            username: $data['username'] ?? null,
            name: $data['name'] ?? null
        );
    }
}
