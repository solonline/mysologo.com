<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\PlatformRevenueCurrencySummary;
use App\Domains\Backoffice\DTOs\PlatformRevenueOverview;
use App\Domains\Wallet\Services\PlatformRevenueService;

class GetPlatformRevenueOverviewQuery
{
    public function __construct(
        protected PlatformRevenueService $platformRevenueService,
    ) {}

    public function execute(?string $from = null, ?string $to = null): PlatformRevenueOverview
    {
        $rows = $this->platformRevenueService->summarize($from, $to);

        $currencies = array_map(
            fn (array $row) => new PlatformRevenueCurrencySummary(
                currency: $row['currency'],
                accruedCents: $row['accrued_cents'],
                settledCents: $row['settled_cents'],
                pendingCents: $row['pending_cents'],
                monthAccruedCents: $row['month_accrued_cents'],
                monthSettledCents: $row['month_settled_cents'],
                monthPendingCents: $row['month_pending_cents'],
                fxCents: $row['fx_cents'],
                monthFxCents: $row['month_fx_cents'],
                transactionCount: $row['transaction_count'],
                fxTransactionCount: $row['fx_transaction_count'],
                settledTransactionCount: $row['settled_transaction_count'],
                pendingTransactionCount: $row['pending_transaction_count'],
                monthlyBreakdown: $row['monthly_breakdown'],
            ),
            $rows,
        );

        return new PlatformRevenueOverview(
            currencies: $currencies,
        );
    }
}
