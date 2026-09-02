<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerWallesterAccount;
use App\Models\StripeTransaction;
use Illuminate\Support\Collection;

class GetWallesterFundingOverviewQuery
{
    /**
     * @return array{
     *     mainAccounts: Collection<int, CustomerWallesterAccount>,
     *     accountsAvailableByCurrency: Collection<string, int>,
     *     topupFundingCounts: array{awaiting_liquidity: int, failed: int, pending: int}
     * }
     */
    public function execute(): array
    {
        return [
            'mainAccounts' => CustomerWallesterAccount::query()
                ->where('is_main', true)
                ->orderBy('funding_currency')
                ->get(['id', 'funding_currency', 'currency_code', 'wallester_account_id', 'available_amount', 'balance']),
            'accountsAvailableByCurrency' => $this->accountsAvailableByCurrency(),
            'topupFundingCounts' => [
                'awaiting_liquidity' => StripeTransaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::AwaitingLiquidity->value)
                    ->count(),
                'failed' => StripeTransaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::Failed->value)
                    ->count(),
                'pending' => StripeTransaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::Pending->value)
                    ->count(),
            ],
        ];
    }

    /**
     * Soma o disponível das contas Wallester, excluindo as contas main.
     *
     * @return Collection<string, int>
     */
    private function accountsAvailableByCurrency(): Collection
    {
        return CustomerWallesterAccount::query()
            ->where('is_main', false)
            ->get(['funding_currency', 'currency_code', 'available_amount'])
            ->groupBy(fn (CustomerWallesterAccount $account): string => $this->accountCurrency($account))
            ->map(fn (Collection $accounts): int => (int) $accounts->sum(
                fn (CustomerWallesterAccount $account): int => (int) ($account->available_amount ?? 0)
            ))
            ->sortKeys();
    }

    private function accountCurrency(CustomerWallesterAccount $account): string
    {
        return strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));
    }
}
