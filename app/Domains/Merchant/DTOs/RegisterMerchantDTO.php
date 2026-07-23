<?php

namespace App\Domains\Merchant\DTOs;

class RegisterMerchantDTO
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $hashedApiKey,
        public readonly string $webhookSecret,
        public readonly string $mode,
        public readonly ?string $webhookUrl,
        public readonly ?array $allowedCurrencies,
    ) {}
}
