<?php

namespace App\DTOs;

class RegisterCustomerDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $type,
        public ?string $username = null,
        public ?string $name = null
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

    /**
     * Retorna apenas os campos do model Customer.
     */
    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'password' => password_hash($this->password, PASSWORD_BCRYPT),
            'type' => $this->type,
            'name' => $this->name, // ou outro campo se necessário
            'username' => $this->username ? str_replace(' ', '_', strtolower($this->username)) : null,
        ], fn($v) => !is_null($v));
    }
}
