<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerBalance;
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
     * Soma o wallester_allocated de todas as subcontas. A master fica de fora.
     *
     * @return Collection<string, int>
     */
    private function accountsAvailableByCurrency(): Collection
    {
        $masterCustomerIds = CustomerWallesterAccount::query()
            ->where('is_main', true)
            ->pluck('customer_id');

        return CustomerBalance::query()
            ->when($masterCustomerIds->isNotEmpty(), fn ($query) => $query->whereNotIn('customer_id', $masterCustomerIds))
            ->selectRaw('currency, SUM(wallester_allocated) as allocated')
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn (CustomerBalance $balance): array => [
                strtoupper((string) $balance->currency) => (int) $balance->allocated,
            ])
            ->sortKeys();
    }
}
