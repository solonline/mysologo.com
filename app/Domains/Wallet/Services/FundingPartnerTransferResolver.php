<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Ebury\Services\EburyFundingTransferDriver;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Contracts\FundingPartnerTransferInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerWallesterAccount;

class FundingPartnerTransferResolver
{
    public function __construct(
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected EburyFundingTransferDriver $eburyDriver,
    ) {}

    public function resolveForCustomer(Customer $customer): FundingPartnerTransferInterface
    {
        $wallesterAccount = $customer->wallesterAccount;

        if ($wallesterAccount instanceof CustomerWallesterAccount
            && $this->isOperational($wallesterAccount->status)) {
            return $this->wallesterDriver;
        }

        $eburyAccount = $customer->eburyAccount;

        if ($eburyAccount instanceof CustomerEburyAccount
            && $this->isOperational($eburyAccount->status)) {
            return $this->eburyDriver;
        }

        throw new ApiException(
            ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
            422,
            'Nenhum parceiro de funding activo encontrado para este cliente.',
        );
    }

    public function resolveProvider(Customer $customer): IntegrationProvider
    {
        return IntegrationProvider::from($this->resolveForCustomer($customer)->provider());
    }

    public function wallester(): WallesterFundingTransferDriver
    {
        return $this->wallesterDriver;
    }

    private function isOperational(IntegrationAccountStatus $status): bool
    {
        return $status->isOperational();
    }
}
