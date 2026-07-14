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
                monthAccruedCents: $row['month_accrued_cents'],
                ledgerCents: $row['ledger_cents'],
                transactionCount: $row['transaction_count'],
                ledgerMatchesStripe: $row['ledger_matches_stripe'],
                monthlyBreakdown: $row['monthly_breakdown'],
            ),
            $rows,
        );

        return new PlatformRevenueOverview(
            currencies: $currencies,
            ledgerMatchesStripe: $this->platformRevenueService->ledgerMatchesStripeGlobally($from, $to),
        );
    }
}
