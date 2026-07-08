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
}
