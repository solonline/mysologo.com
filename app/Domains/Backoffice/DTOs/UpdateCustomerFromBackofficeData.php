<?php

namespace App\Domains\Backoffice\DTOs;

readonly class UpdateCustomerFromBackofficeData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $currency,
        public ?string $language,
        public string $status,
        public ?string $docStatus,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            phone: $validated['phone'] ?? null,
            currency: $validated['currency'] ?? null,
            language: $validated['language'] ?? null,
            status: $validated['status'],
            docStatus: $validated['doc_status'] ?? null,
        );
    }
}
