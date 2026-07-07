<?php

namespace App\Domains\Wallet\Contracts;

use App\Models\Customer;

interface FundingPartnerTransferInterface
{
    public function provider(): string;

    public function transferBetweenCustomers(
        Customer $from,
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
    ): void;

    public function transferFromMasterToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void;

    public function getAvailableBalance(Customer $customer, string $currency): int;
}
