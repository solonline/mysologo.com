<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Domains\Wallet\Services\WallesterMainAccountSyncService;
use App\Models\StripeTransaction;

class RetryAwaitingWallesterTopupsAction
{
    public function __construct(
        protected WallesterMainAccountSyncService $mainAccountSync,
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}

    /**
     * @return array{synced_main: int, dispatched: int, skipped: int}
     */
    public function execute(): array
    {
        $syncedMain = $this->mainAccountSync->syncAll();

        $dispatched = 0;
        $skipped = 0;

        StripeTransaction::query()
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->whereIn('partner_funding_status', [
                PartnerFundingStatus::Pending->value,
                PartnerFundingStatus::AwaitingLiquidity->value,
                PartnerFundingStatus::Failed->value,
            ])
            ->where('net_amount', '>', 0)
            ->orderBy('id')
            ->each(function (StripeTransaction $transaction) use (&$dispatched, &$skipped): void {
                if (! $this->fundingPolicy->isRetriable($transaction)) {
                    $skipped++;

                    return;
                }

                FundWallesterTopupFromStripeJob::dispatch($transaction->id);
                $dispatched++;
            });

        return [
            'synced_main' => $syncedMain,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }
}
