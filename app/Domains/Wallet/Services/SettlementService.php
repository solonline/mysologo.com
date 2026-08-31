<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    public function __construct(
        protected TopupWallesterFundingPolicy $topupFundingPolicy,
    ) {}

    /**
     * Cron fallback/retry after payout.paid became the settlement source of truth.
     * Does not credit wallet by available_on — only re-dispatches retriable partner funding
     * and reports transactions still awaiting Stripe payout past available_on.
     *
     * @return array{retried: int, skipped: int, awaiting_payout_stale: int}
     */
    public function runDailySettlement(Carbon $date): array
    {
        $retried = 0;
        $skipped = 0;

        StripeTransaction::query()
            ->where('status', 'succeeded')
            ->whereIn('type', [
                TransactionType::TOPUP->value,
                TransactionType::MERCHANT_PAYMENT->value,
            ])
            ->whereIn('partner_funding_status', [
                PartnerFundingStatus::Pending->value,
                PartnerFundingStatus::AwaitingLiquidity->value,
                PartnerFundingStatus::Failed->value,
            ])
            ->where(function ($query): void {
                $query->where('wallester_funding_net_amount', '>', 0)
                    ->orWhere(function ($inner): void {
                        $inner->where('type', TransactionType::TOPUP->value)
                            ->where('net_amount', '>', 0);
                    });
            })
            ->orderBy('id')
            ->each(function (StripeTransaction $transaction) use (&$retried, &$skipped): void {
                if (! $this->topupFundingPolicy->isRetriable($transaction)) {
                    $skipped++;

                    return;
                }

                if ($this->topupFundingPolicy->hasPendingMovement($transaction)) {
                    $skipped++;

                    return;
                }

                FundWallesterTopupFromStripeJob::dispatch($transaction->id);
                $retried++;
            });

        $awaitingPayoutStale = StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('partner_funding_status', PartnerFundingStatus::AwaitingStripePayout->value)
            ->whereNotNull('available_on')
            ->whereDate('available_on', '<=', $date)
            ->count();

        if ($awaitingPayoutStale > 0) {
            Log::channel('stripe')->warning('SettlementService: transactions still awaiting Stripe payout past available_on', [
                'date' => $date->toDateString(),
                'count' => $awaitingPayoutStale,
            ]);
        }

        Log::channel('stripe')->info('SettlementService: daily fallback/retry completed', [
            'date' => $date->toDateString(),
            'retried' => $retried,
            'skipped' => $skipped,
            'awaiting_payout_stale' => $awaitingPayoutStale,
        ]);

        return [
            'retried' => $retried,
            'skipped' => $skipped,
            'awaiting_payout_stale' => $awaitingPayoutStale,
            // Keep legacy keys for command output compatibility.
            'batches' => 0,
            'transactions' => $retried,
        ];
    }
}
