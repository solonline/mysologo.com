<?php

namespace App\Domains\Customer\DTOs;

class RegisterCustomerDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $phone,
        public readonly string $type,
        public readonly ?string $username = null,
        public readonly ?string $name = null,
        public readonly ?string $language = null,
        public readonly ?string $currency = null,
        public readonly ?string $referralCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            phone: $data['phone'],
            type: $data['type'],
            username: $data['username'] ?? null,
            name: $data['name'] ?? null,
            language: $data['language'] ?? null,
            currency: $data['currency'] ?? null,
            referralCode: $data['referral_code'] ?? null,
        );
    }
}
