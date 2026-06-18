<?php

namespace App\Domains\Backoffice\DTOs;

readonly class StripeFeeData
{
    public function __construct(
        public string $paymentMethod,
        public string $label,
        public float $stripeFeePercentage,
        public float $stripeFeeFixed,
        public float $platformFeePercentage,
        public float $platformFeeFixed,
        public string $currency,
        public bool $isActive,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated, bool $isActive): self
    {
        return new self(
            paymentMethod: $validated['payment_method'],
            label: $validated['label'],
            stripeFeePercentage: ((float) ($validated['stripe_fee_percentage'] ?? 0)) / 100,
            stripeFeeFixed: (float) ($validated['stripe_fee_fixed'] ?? 0),
            platformFeePercentage: ((float) $validated['platform_fee_percentage']) / 100,
            platformFeeFixed: (float) $validated['platform_fee_fixed'],
            currency: strtoupper($validated['currency']),
            isActive: $isActive,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'payment_method' => $this->paymentMethod,
            'label' => $this->label,
            'stripe_fee_percentage' => $this->stripeFeePercentage,
            'stripe_fee_fixed' => $this->stripeFeeFixed,
            'platform_fee_percentage' => $this->platformFeePercentage,
            'platform_fee_fixed' => $this->platformFeeFixed,
            'currency' => $this->currency,
            'is_active' => $this->isActive,
        ];
    }
}
