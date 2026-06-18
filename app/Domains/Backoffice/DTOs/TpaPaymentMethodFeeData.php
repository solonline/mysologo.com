<?php

namespace App\Domains\Backoffice\DTOs;

readonly class TpaPaymentMethodFeeData
{
    public function __construct(
        public string $paymentMethod,
        public string $label,
        public float $feePercentage,
        public float $feeFixed,
        public string $currency,
        public bool $isActive,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated, bool $isActive): self
    {
        return new self(
            paymentMethod: $validated['payment_method'],
            label: $validated['label'],
            feePercentage: (float) $validated['fee_percentage'],
            feeFixed: (float) $validated['fee_fixed'],
            currency: strtoupper($validated['currency']),
            isActive: $isActive,
        );
    }
}
