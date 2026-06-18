<?php

namespace App\Domains\Backoffice\DTOs;

readonly class StaffUserData
{
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public ?string $phone,
        public ?string $password,
        public ?string $role,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated, bool $requirePassword = false): self
    {
        return new self(
            name: $validated['name'],
            username: $validated['username'],
            email: $validated['email'],
            phone: $validated['phone'] ?? null,
            password: $validated['password'] ?? null,
            role: $validated['role'] ?? null,
        );
    }
}
