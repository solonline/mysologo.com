<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
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

        $account->update([
            'balance' => $this->moneyToCents($response['balance'] ?? null),
            'available_amount' => $this->moneyToCents($response['available_amount'] ?? null),
            'blocked_amount' => $this->moneyToCents($response['blocked_amount'] ?? null),
            'blocked_credit_amount' => $this->moneyToCents($response['blocked_credit_amount'] ?? null),
            'credit_limit' => $this->moneyToCents($response['credit_limit'] ?? null),
            'used_credit' => $this->moneyToCents($response['used_credit'] ?? null),
        ]);
    }

    private function moneyToCents(float|int|string|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return $this->driver->amountToCents($amount);
    }
}
