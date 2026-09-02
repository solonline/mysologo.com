<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\StripeTransaction;
use Illuminate\Console\Command;

class RunPlatformRevenueReportCommand extends Command
{
    protected $signature = 'wallet:platform-revenue
                            {--currency= : Filter by ISO currency code}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--limit=10 : Number of recent fee transactions to list}';

    protected $description = 'Report platform fee revenue from Stripe transactions and Wallester FX fees.';

    public function __construct(
        private readonly PlatformRevenueService $platformRevenueService,
        private readonly TopupWallesterFundingPolicy $fundingPolicy,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = $this->option('from') ? (string) $this->option('from') : null;
        $to = $this->option('to') ? (string) $this->option('to') : null;
        $currencyFilter = $this->option('currency') ? strtoupper((string) $this->option('currency')) : null;
        $limit = max(1, (int) $this->option('limit'));

        $summaries = $this->platformRevenueService->summarize($from, $to);

        if ($summaries === []) {
            $this->warn('No platform fee revenue found.');

            return self::SUCCESS;
        }

        $this->info('Platform revenue report');
        $this->line(sprintf(
            'Scope: from=%s to=%s currency=%s',
            $from ?? 'any',
            $to ?? 'any',
            $currencyFilter ?? 'any',
        ));

        foreach ($summaries as $row) {
            if ($currencyFilter !== null && $row['currency'] !== $currencyFilter) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=cyan>{$row['currency']}</>");

            $this->table(['Metric', 'Value'], [
                ['Total billed (Stripe + FX)', $this->formatMoney($row['accrued_cents'] + $row['fx_cents'], $row['currency'])],
                ['Stripe (platform_fee)', $this->formatMoney($row['accrued_cents'], $row['currency'])],
                ['Settled', $this->formatMoney($row['settled_cents'], $row['currency'])],
                ['Pending settlement', $this->formatMoney($row['pending_cents'], $row['currency'])],
                ['FX Yoop', $this->formatMoney($row['fx_cents'], $row['currency'])],
                ['This month (total)', $this->formatMoney($row['month_accrued_cents'] + $row['month_fx_cents'], $row['currency'])],
                ['This month (Stripe)', $this->formatMoney($row['month_accrued_cents'], $row['currency'])],
                ['This month (FX)', $this->formatMoney($row['month_fx_cents'], $row['currency'])],
                ['Transactions', (string) ($row['transaction_count'] + $row['fx_transaction_count'])],
            ]);
        }

        $this->newLine();
        $this->info("Recent transactions with platform_fee (limit {$limit})");

        $recent = $this->fundingPolicy->constrainAfterAccountingCutoff(
            StripeTransaction::query()
                ->with('customer')
                ->where('status', 'succeeded')
                ->where('platform_fee', '>', 0)
                ->when($currencyFilter !== null, fn ($q) => $q->where('currency', $currencyFilter))
                ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
        )
            ->latest('created_at')
            ->limit($limit)
            ->get();

        if ($recent->isEmpty()) {
            $this->line('(none)');
        } else {
            $this->table(
                ['ID', 'Date', 'Customer', 'Gross', 'Platform fee', 'Currency'],
                $recent->map(fn (StripeTransaction $tx) => [
                    $tx->id,
                    ($tx->stripe_created_at ?? $tx->created_at)?->toDateTimeString() ?? '—',
                    $tx->customer?->name ?? '—',
                    $this->formatMoney((int) $tx->amount, $tx->currency),
                    $this->formatMoney((int) $tx->platform_fee, $tx->currency),
                    strtoupper($tx->currency),
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    private function formatMoney(int $cents, string $currency): string
    {
        return sprintf('%s %.2f', strtoupper($currency), $cents / 100);
    }
}
