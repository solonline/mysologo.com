<?php

namespace App\DTOs;

class SettingDTO
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: trim($data['key']),
            value: $data['value']
        );
    }
}
