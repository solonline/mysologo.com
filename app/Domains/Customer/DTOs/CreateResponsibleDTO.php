<?php

namespace App\Domains\Customer\DTOs;

class CreateResponsibleDTO
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $name,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'],
            email: $data['email'],
            name: $data['name'],
        );
    }
}
