<?php

namespace App\Domains\Backoffice\DTOs;

use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Carbon\CarbonInterface;

readonly class CustomerActivityItem
{
    public function __construct(
        public string $source,
        public CarbonInterface $occurredAt,
        public bool $isIncoming,
        public int $amountCents,
        public ?int $netAmountCents,
        public string $currency,
        public string $typeLabel,
        public string $status,
        public ?string $description = null,
        public ?string $bankTransferSenderName = null,
        public ?string $bankTransferIbanLast4 = null,
        public ?string $p2pCounterpartyName = null,
        public ?string $p2pCounterpartyUsername = null,
        public ?string $p2pCounterpartyRole = null,
        public ?StripeTransaction $stripeTransaction = null,
        public ?FundingPartnerMovement $wallesterMovement = null,
        public ?string $cardLabel = null,
        public ?string $merchantCity = null,
        public ?string $merchantCategoryCode = null,
        public ?string $sourceLabel = null,
        public ?string $fxDetailLabel = null,
        public ?int $feeCents = null,
        public ?CarbonInterface $availableOn = null,
        public bool $fundsAvailable = false,
    ) {}
}
