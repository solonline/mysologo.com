<?php

namespace App\Domains\Merchant\DTOs;

use Carbon\Carbon;

class CreateMerchantPaymentRequestDTO
{
    /**
     * @param  list<string>|null  $paymentMethodTypes
     * @param  array<string, mixed>|null  $metadata
     * @param  array<int, mixed>|null  $items
     */
    public function __construct(
        public readonly int $merchantId,
        public readonly ?int $payerId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $description,
        public readonly ?string $externalReference,
        public readonly ?string $webhookUrl,
        public readonly ?array $metadata,
        public readonly ?array $items,
        public readonly Carbon $expiresAt,
        public readonly ?string $language = null,
        public readonly ?array $paymentMethodTypes = null,
    ) {}
}
