<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PlatformRevenueService
{
    public const LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX = 'Platform fee from transaction #';

    public const LEDGER_PLATFORM_FEE_REVERSAL_PREFIX = 'Platform fee reversal #';

    public function __construct(
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}

    /**
     * @return list<array{
     *     currency: string,
     *     accrued_cents: int,
     *     settled_cents: int,
     *     pending_cents: int,
     *     month_accrued_cents: int,
     *     month_settled_cents: int,
     *     month_pending_cents: int,
     *     fx_cents: int,
     *     month_fx_cents: int,
     *     transaction_count: int,
     *     fx_transaction_count: int,
     *     settled_transaction_count: int,
     *     pending_transaction_count: int,
     *     monthly_breakdown: list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int, fx_cents: int}>
     * }>
     */
    public function summarize(?string $from = null, ?string $to = null): array
    {
        $monthStart = now()->startOfMonth();
        $summaries = [];

        foreach ($this->resolveCurrencies($from, $to) as $currency) {
            $summaries[] = [
                'currency' => $currency,
                'accrued_cents' => $this->sumStripePlatformFees($currency, $from, $to),
                'settled_cents' => $this->sumStripePlatformFees($currency, $from, $to, settled: true),
                'pending_cents' => $this->sumStripePlatformFees($currency, $from, $to, settled: false),
                'month_accrued_cents' => $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to),
                'month_settled_cents' => $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to, settled: true),
                'month_pending_cents' => $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to, settled: false),
                'fx_cents' => $this->sumFxPlatformFees($currency, $from, $to),
                'month_fx_cents' => $this->sumFxPlatformFees($currency, $monthStart->toDateString(), $to),
                'transaction_count' => $this->countStripePlatformFeeTransactions($currency, $from, $to),
                'fx_transaction_count' => (int) $this->fxPlatformFeesQuery($currency, $from, $to)->count(),
                'settled_transaction_count' => $this->countStripePlatformFeeTransactions($currency, $from, $to, settled: true),
                'pending_transaction_count' => $this->countStripePlatformFeeTransactions($currency, $from, $to, settled: false),
                'monthly_breakdown' => $this->monthlyBreakdown($currency, $from, $to),
            ];
        }

        return $summaries;
    }

    /**
     * @return list<string>
     */
    private function resolveCurrencies(?string $from, ?string $to): array
    {
        $fromStripe = $this->fundingPolicy->constrainAfterAccountingCutoff(
            StripeTransaction::query()
                ->where('status', 'succeeded')
                ->where('platform_fee', '>', 0)
                ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
        )
            ->distinct()
            ->pluck('currency');

        $fromFx = $this->fxPlatformFeesQuery(null, $from, $to)
            ->distinct()
            ->pluck('currency');

        return $fromStripe
            ->merge($fromFx)
            ->map(fn (string $currency) => strtoupper($currency))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function sumStripePlatformFees(
        string $currency,
        ?string $from,
        ?string $to,
        ?bool $settled = null,
    ): int {
        return (int) $this->platformFeeTransactionsQuery($currency, $from, $to, $settled)
            ->sum('platform_fee');
    }

    private function sumFxPlatformFees(string $currency, ?string $from, ?string $to): int
    {
        return (int) $this->fxPlatformFeesQuery($currency, $from, $to)->sum('platform_fees_amount');
    }

    private function fxPlatformFeesQuery(?string $currency, ?string $from, ?string $to): Builder
    {
        return FundingPartnerMovement::query()
            ->where('type', FundingPartnerMovementType::CardPurchase)
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->where('platform_fees_amount', '>', 0)
            ->when($currency !== null, fn ($q) => $q->where('currency', strtoupper($currency)))
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }

    private function countStripePlatformFeeTransactions(
        string $currency,
        ?string $from,
        ?string $to,
        ?bool $settled = null,
    ): int {
        return (int) $this->platformFeeTransactionsQuery($currency, $from, $to, $settled)->count();
    }

    private function platformFeeTransactionsQuery(
        string $currency,
        ?string $from,
        ?string $to,
        ?bool $settled = null,
    ): Builder {
        return $this->fundingPolicy->constrainAfterAccountingCutoff(
            StripeTransaction::query()
                ->where('status', 'succeeded')
                ->where('platform_fee', '>', 0)
                ->where('currency', strtoupper($currency))
                ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->when($settled === true, fn ($q) => $q->whereNotNull('balance_settled_at'))
                ->when($settled === false, fn ($q) => $q->whereNull('balance_settled_at'))
        );
    }

    /**
     * @return list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int, fx_cents: int}>
     */
    private function monthlyBreakdown(string $currency, ?string $from, ?string $to): array
    {
        $rangeEnd = $to !== null ? Carbon::parse($to)->endOfMonth() : now()->endOfMonth();
        $rangeStart = $from !== null
            ? Carbon::parse($from)->startOfMonth()
            : $rangeEnd->copy()->subMonths(5)->startOfMonth();

        $totalsByMonth = [];
        $emptyBucket = static fn (): array => [
            'amount_cents' => 0,
            'settled_cents' => 0,
            'pending_cents' => 0,
            'fx_cents' => 0,
        ];

        $this->fundingPolicy->constrainAfterAccountingCutoff(
            StripeTransaction::query()
                ->where('status', 'succeeded')
                ->where('platform_fee', '>', 0)
                ->where('currency', strtoupper($currency))
                ->whereDate('created_at', '>=', $rangeStart->toDateString())
                ->whereDate('created_at', '<=', $rangeEnd->toDateString())
        )
            ->get(['platform_fee', 'created_at', 'balance_settled_at'])
            ->each(function (StripeTransaction $transaction) use (&$totalsByMonth, $emptyBucket): void {
                $key = $transaction->created_at->format('Y-m');
                $bucket = $totalsByMonth[$key] ??= $emptyBucket();
                $fee = (int) $transaction->platform_fee;
                $bucket['amount_cents'] += $fee;
                if ($transaction->balance_settled_at !== null) {
                    $bucket['settled_cents'] += $fee;
                } else {
                    $bucket['pending_cents'] += $fee;
                }
                $totalsByMonth[$key] = $bucket;
            });

        $this->fxPlatformFeesQuery($currency, $rangeStart->toDateString(), $rangeEnd->toDateString())
            ->get(['platform_fees_amount', 'created_at'])
            ->each(function (FundingPartnerMovement $movement) use (&$totalsByMonth, $emptyBucket): void {
                $key = $movement->created_at->format('Y-m');
                $bucket = $totalsByMonth[$key] ??= $emptyBucket();
                $fee = (int) $movement->platform_fees_amount;
                $bucket['amount_cents'] += $fee;
                $bucket['fx_cents'] += $fee;
                $totalsByMonth[$key] = $bucket;
            });

        $breakdown = [];
        $cursor = $rangeStart->copy();

        while ($cursor <= $rangeEnd) {
            $key = $cursor->format('Y-m');
            $bucket = $totalsByMonth[$key] ?? $emptyBucket();
            $breakdown[] = [
                'month' => $key,
                'amount_cents' => (int) $bucket['amount_cents'],
                'settled_cents' => (int) $bucket['settled_cents'],
                'pending_cents' => (int) $bucket['pending_cents'],
                'fx_cents' => (int) $bucket['fx_cents'],
            ];
            $cursor->addMonth();
        }

        return $breakdown;
    }
}
