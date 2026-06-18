<?php

namespace App\Domains\Backoffice\DTOs;

readonly class RoleData
{
    /**
     * @param  list<string>|null  $permissions
     */
    public function __construct(
        public string $name,
        public ?array $permissions,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'],
            permissions: $validated['permissions'] ?? null,
        );
    }
}
