<?php

namespace App\Domains\Ebury\Services;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Contracts\FundingPartnerTransferInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use RuntimeException;

class EburyFundingTransferDriver implements FundingPartnerTransferInterface
{
    public function provider(): string
    {
        return IntegrationProvider::EBURY->value;
    }

    public function transferBetweenCustomers(
        Customer $from,
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
    ): void {
        throw new RuntimeException('Ebury funding transfers are not implemented yet.');
    }

    public function transferFromMasterToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        throw new RuntimeException('Ebury funding transfers are not implemented yet.');
    }

    public function getAvailableBalance(Customer $customer, string $currency): int
    {
        throw new ApiException(
            ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
            422,
            'Saldo Ebury ainda não disponível.',
        );
    }
}
