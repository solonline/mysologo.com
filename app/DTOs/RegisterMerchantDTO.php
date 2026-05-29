<?php

namespace App\DTOs;

class RegisterMerchantDTO
{
    public function __construct(
        public readonly int     $customerId,
        public readonly string  $name,
        public readonly string  $slug,
        public readonly string  $hashedApiKey,
        public readonly string  $mode,
        public readonly ?string $webhookUrl,
        public readonly ?array  $allowedCurrencies,
    ) {}
}
