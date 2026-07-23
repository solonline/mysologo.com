<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PlatformRevenueService
{
    public const LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX = 'Platform fee from transaction #';

    public const LEDGER_PLATFORM_FEE_REVERSAL_PREFIX = 'Platform fee reversal #';

    /**
     * @return list<array{
     *     currency: string,
     *     accrued_cents: int,
     *     settled_cents: int,
     *     pending_cents: int,
     *     month_accrued_cents: int,
     *     month_settled_cents: int,
     *     month_pending_cents: int,
     *     ledger_cents: int,
     *     transaction_count: int,
     *     settled_transaction_count: int,
     *     pending_transaction_count: int,
     *     ledger_matches_stripe: bool,
     *     monthly_breakdown: list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int}>
     * }>
     */
    public function summarize(?string $from = null, ?string $to = null): array
    {
        $platformCustomer = $this->resolvePlatformCustomer();

        if ($platformCustomer === null) {
            return [];
        }

        $monthStart = now()->startOfMonth();
        $currencies = $this->resolveCurrencies($platformCustomer->id, $from, $to);
        $summaries = [];

        foreach ($currencies as $currency) {
            $accruedCents = $this->sumStripePlatformFees($currency, $from, $to);
            $settledCents = $this->sumStripePlatformFees($currency, $from, $to, settled: true);
            $pendingCents = $this->sumStripePlatformFees($currency, $from, $to, settled: false);
            $monthAccruedCents = $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to);
            $monthSettledCents = $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to, settled: true);
            $monthPendingCents = $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to, settled: false);
            $ledgerCents = $this->sumLedgerPlatformFees($platformCustomer->id, $currency, $from, $to);
            $transactionCount = $this->countStripePlatformFeeTransactions($currency, $from, $to);
            $settledTransactionCount = $this->countStripePlatformFeeTransactions($currency, $from, $to, settled: true);
            $pendingTransactionCount = $this->countStripePlatformFeeTransactions($currency, $from, $to, settled: false);

            $summaries[] = [
                'currency' => $currency,
                'accrued_cents' => $accruedCents,
                'settled_cents' => $settledCents,
                'pending_cents' => $pendingCents,
                'month_accrued_cents' => $monthAccruedCents,
                'month_settled_cents' => $monthSettledCents,
                'month_pending_cents' => $monthPendingCents,
                'ledger_cents' => $ledgerCents,
                'transaction_count' => $transactionCount,
                'settled_transaction_count' => $settledTransactionCount,
                'pending_transaction_count' => $pendingTransactionCount,
                'ledger_matches_stripe' => $accruedCents === $ledgerCents,
                'monthly_breakdown' => $this->monthlyBreakdown($currency, $from, $to),
            ];
        }

        return $summaries;
    }

    public function ledgerMatchesStripeGlobally(?string $from = null, ?string $to = null): bool
    {
        foreach ($this->summarize($from, $to) as $row) {
            if (! $row['ledger_matches_stripe']) {
                return false;
            }
        }

        return true;
    }

    private function resolvePlatformCustomer(): ?Customer
    {
        return Cache::rememberForever('platform_customer', fn () => Customer::query()
            ->where('type', 'platform')
            ->first());
    }

    /**
     * @return list<string>
     */
    private function resolveCurrencies(int $platformCustomerId, ?string $from, ?string $to): array
    {
        $fromStripe = StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->distinct()
            ->pluck('currency');

        $fromLedger = LedgerEntry::query()
            ->where('customer_id', $platformCustomerId)
            ->where('entry_type', LedgerEntryType::MANUAL_CREDIT->value)
            ->where('description', 'like', self::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.'%')
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->distinct()
            ->pluck('currency');

        return $fromStripe
            ->merge($fromLedger)
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

    private function sumLedgerPlatformFees(int $platformCustomerId, string $currency, ?string $from, ?string $to): int
    {
        $credits = (int) LedgerEntry::query()
            ->where('customer_id', $platformCustomerId)
            ->where('currency', strtoupper($currency))
            ->where('entry_type', LedgerEntryType::MANUAL_CREDIT->value)
            ->where('description', 'like', self::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.'%')
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->sum('amount');

        $reversals = (int) LedgerEntry::query()
            ->where('customer_id', $platformCustomerId)
            ->where('currency', strtoupper($currency))
            ->where('entry_type', LedgerEntryType::MANUAL_DEBIT->value)
            ->where('description', 'like', self::LEDGER_PLATFORM_FEE_REVERSAL_PREFIX.'%')
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->sum('amount');

        return $credits - $reversals;
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
        return StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->where('currency', strtoupper($currency))
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($settled === true, fn ($q) => $q->whereNotNull('balance_settled_at'))
            ->when($settled === false, fn ($q) => $q->whereNull('balance_settled_at'));
    }

    /**
     * @return list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int}>
     */
    private function monthlyBreakdown(string $currency, ?string $from, ?string $to): array
    {
        $rangeEnd = $to !== null ? Carbon::parse($to)->endOfMonth() : now()->endOfMonth();
        $rangeStart = $from !== null
            ? Carbon::parse($from)->startOfMonth()
            : $rangeEnd->copy()->subMonths(5)->startOfMonth();

        $totalsByMonth = [];

        StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->where('currency', strtoupper($currency))
            ->whereDate('created_at', '>=', $rangeStart->toDateString())
            ->whereDate('created_at', '<=', $rangeEnd->toDateString())
            ->get(['platform_fee', 'created_at', 'balance_settled_at'])
            ->each(function (StripeTransaction $transaction) use (&$totalsByMonth): void {
                $key = $transaction->created_at->format('Y-m');
                $bucket = $totalsByMonth[$key] ??= [
                    'amount_cents' => 0,
                    'settled_cents' => 0,
                    'pending_cents' => 0,
                ];
                $fee = (int) $transaction->platform_fee;
                $bucket['amount_cents'] += $fee;
                if ($transaction->balance_settled_at !== null) {
                    $bucket['settled_cents'] += $fee;
                } else {
                    $bucket['pending_cents'] += $fee;
                }
                $totalsByMonth[$key] = $bucket;
            });

        $breakdown = [];
        $cursor = $rangeStart->copy();

        while ($cursor <= $rangeEnd) {
            $key = $cursor->format('Y-m');
            $bucket = $totalsByMonth[$key] ?? [
                'amount_cents' => 0,
                'settled_cents' => 0,
                'pending_cents' => 0,
            ];
            $breakdown[] = [
                'month' => $key,
                'amount_cents' => (int) $bucket['amount_cents'],
                'settled_cents' => (int) $bucket['settled_cents'],
                'pending_cents' => (int) $bucket['pending_cents'],
            ];
            $cursor->addMonth();
        }

        return $breakdown;
    }
}
