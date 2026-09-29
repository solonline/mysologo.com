<?php

namespace App\Domains\Wallet\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerTransferReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $toCustomerId,
        public readonly int $fromCustomerId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly int $movementId,
        public readonly ?string $description = null,
        public readonly bool $sendBothSides = false,
    ) {}
}
