<?php

namespace App\Domains\Customer\DTOs;

class UpdateCustomerDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $username = null,
        public readonly ?string $avatar = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $password = null,
        public readonly ?string $referalEmailCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            username: $data['username'] ?? null,
            avatar: $data['avatar'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            password: $data['password'] ?? null,
            referalEmailCode: $data['referal_email_code'] ?? null,
        );
    }
}
