<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;

class WallesterMainAccountSyncService
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected WallesterFundingTransferDriver $driver,
    ) {}

    public function syncAll(): int
    {
        $synced = 0;

        CustomerWallesterAccount::query()
            ->where('is_main', true)
            ->each(function (CustomerWallesterAccount $account) use (&$synced): void {
                $this->syncAccount($account);
                $synced++;
            });

        return $synced;
    }

    public function syncAccount(CustomerWallesterAccount $account): void
    {
        $currency = strtoupper((string) ($account->funding_currency ?: $account->currency_code));

        $response = $this->gateway
            ->forCurrency($currency)
            ->getAccount($account->wallester_account_id, 'platform');

        $account->syncBalancesFromWallesterResponse(
            $response,
            fn (float|int|string|null $amount): ?int => $this->moneyToCents($amount),
        );
    }

    public function syncForCustomer(Customer $customer, ?string $currency = null): bool
    {
        $account = $currency !== null
            ? $customer->wallesterAccountFor(strtoupper($currency))
            : $customer->wallesterAccount;

        if (! $account instanceof CustomerWallesterAccount || ! $account->status->isOperational()) {
            return false;
        }

        $routingCurrency = strtoupper((string) ($account->funding_currency ?: $account->currency_code));

        $response = $this->gateway
            ->forCurrency($routingCurrency)
            ->getAccount($account->wallester_account_id, (string) $customer->id);

        $account->syncBalancesFromWallesterResponse(
            $response,
            fn (float|int|string|null $amount): ?int => $this->moneyToCents($amount),
        );

        return true;
    }

    private function moneyToCents(float|int|string|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return $this->driver->amountToCents($amount);
    }
}
