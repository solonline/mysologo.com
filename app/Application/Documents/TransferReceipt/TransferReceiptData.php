<?php

namespace App\Application\Documents\TransferReceipt;

use App\Models\FundingPartnerMovement;

final readonly class TransferReceiptData
{
    public function __construct(
        public string $movementId,
        public string $amount,
        public string $currency,
        public string $toCustomerUsername,
        public string $fromCustomerUsername,
        public string $date,
        public ?string $description = null,
    ) {}

    public static function fromMovement(FundingPartnerMovement $movement): static
    {
        $groupedTransactions = $movement->groupedTransactions;
        $fromMovement = $groupedTransactions->first(
            fn (FundingPartnerMovement $item): bool => $item->direction === \App\Domains\Wallet\Enums\FundingPartnerMovementDirection::Out,
        );
        $toMovement = $groupedTransactions->first(
            fn (FundingPartnerMovement $item): bool => $item->direction === \App\Domains\Wallet\Enums\FundingPartnerMovementDirection::In,
        );
        $fromCustomer = $fromMovement?->fundingAccount?->customer;
        $toCustomer = $toMovement?->fundingAccount?->customer;

        if ($fromCustomer === null || $toCustomer === null) {
            throw new \UnexpectedValueException('Transfer receipt requires customer accounts on both sides of the movement.');
        }

        $description = filled($movement->description) ? (string) $movement->description : null;

        return new self(
            movementId: (string) $movement->id,
            amount: number_format(((int) $movement->amount) / 100, 2, ',', '.'),
            currency: strtoupper((string) $movement->currency),
            toCustomerUsername: (string) $toCustomer->username,
            fromCustomerUsername: (string) $fromCustomer->username,
            date: $movement->created_at->format('d/m/Y').' às '.$movement->created_at->format('H:i'),
            description: $description,
        );
    }
}
