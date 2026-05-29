<?php

namespace App\DTOs;

use Carbon\Carbon;

class CreateMerchantPaymentRequestDTO
{
    public function __construct(
        public readonly int     $merchantId,
        public readonly ?int    $payerId,
        public readonly int     $amount,
        public readonly string  $currency,
        public readonly ?string $description,
        public readonly ?string $externalReference,
        public readonly ?string $callbackUrl,
        public readonly ?array  $metadata,
        public readonly ?array  $items,
        public readonly Carbon  $expiresAt,
        public readonly ?string $language = null,
    ) {}
}
