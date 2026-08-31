<?php

namespace App\Domains\Backoffice\DTOs;

readonly class CreateCustomerFromBackofficeData
{
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public string $phone,
        public string $password,
        public string $type,
        public string $language,
        public string $currency,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'],
            username: $validated['username'],
            email: $validated['email'],
            phone: $validated['phone'],
            password: $validated['password'],
            type: $validated['type'],
            language: $validated['language'] ?? 'pt',
            currency: strtoupper($validated['currency'] ?? 'EUR'),
        );
    }
}
