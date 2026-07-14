<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PlatformRevenueService
{
    public const LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX = 'Platform fee from transaction #';

    /**
     * @return list<array{
     *     currency: string,
     *     accrued_cents: int,
     *     month_accrued_cents: int,
     *     ledger_cents: int,
     *     transaction_count: int,
     *     ledger_matches_stripe: bool,
     *     monthly_breakdown: list<array{month: string, amount_cents: int}>
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
            $monthAccruedCents = $this->sumStripePlatformFees($currency, $monthStart->toDateString(), $to);
            $ledgerCents = $this->sumLedgerPlatformFees($platformCustomer->id, $currency, $from, $to);
            $transactionCount = $this->countStripePlatformFeeTransactions($currency, $from, $to);

            $summaries[] = [
                'currency' => $currency,
                'accrued_cents' => $accruedCents,
                'month_accrued_cents' => $monthAccruedCents,
                'ledger_cents' => $ledgerCents,
                'transaction_count' => $transactionCount,
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

    private function sumStripePlatformFees(string $currency, ?string $from, ?string $to): int
    {
        return (int) StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->where('currency', strtoupper($currency))
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->sum('platform_fee');
    }

    private function sumLedgerPlatformFees(int $platformCustomerId, string $currency, ?string $from, ?string $to): int
    {
        return (int) LedgerEntry::query()
            ->where('customer_id', $platformCustomerId)
            ->where('currency', strtoupper($currency))
            ->where('entry_type', LedgerEntryType::MANUAL_CREDIT->value)
            ->where('description', 'like', self::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.'%')
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->sum('amount');
    }

    private function countStripePlatformFeeTransactions(string $currency, ?string $from, ?string $to): int
    {
        return (int) StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->where('currency', strtoupper($currency))
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->count();
    }

    /**
     * @return list<array{month: string, amount_cents: int}>
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
            ->get(['platform_fee', 'created_at'])
            ->each(function (StripeTransaction $transaction) use (&$totalsByMonth): void {
                $key = $transaction->created_at->format('Y-m');
                $totalsByMonth[$key] = ($totalsByMonth[$key] ?? 0) + (int) $transaction->platform_fee;
            });

        $breakdown = [];
        $cursor = $rangeStart->copy();

        while ($cursor <= $rangeEnd) {
            $key = $cursor->format('Y-m');
            $breakdown[] = [
                'month' => $key,
                'amount_cents' => (int) ($totalsByMonth[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $breakdown;
    }
}
