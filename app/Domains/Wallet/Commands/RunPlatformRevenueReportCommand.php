<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\StripeTransaction;
use Illuminate\Console\Command;

class RunPlatformRevenueReportCommand extends Command
{
    protected $signature = 'wallet:platform-revenue
                            {--currency= : Filter by ISO currency code}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--limit=10 : Number of recent fee transactions to list}';

    protected $description = 'Report platform fee revenue from Stripe transactions and ledger reconciliation.';

    public function __construct(
        private readonly PlatformRevenueService $platformRevenueService,
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

        $hasMismatch = false;

        foreach ($summaries as $row) {
            if ($currencyFilter !== null && $row['currency'] !== $currencyFilter) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=cyan>{$row['currency']}</>");

            $rows = [
                ['Stripe (platform_fee)', $this->formatMoney($row['accrued_cents'], $row['currency'])],
                ['Ledger (manual_credit)', $this->formatMoney($row['ledger_cents'], $row['currency'])],
                ['Delta', $this->formatMoney($row['accrued_cents'] - $row['ledger_cents'], $row['currency'])],
                ['This month', $this->formatMoney($row['month_accrued_cents'], $row['currency'])],
                ['Transactions', (string) $row['transaction_count']],
                ['Consistent', $row['ledger_matches_stripe'] ? 'yes' : 'NO'],
            ];

            $this->table(['Metric', 'Value'], $rows);

            if (! $row['ledger_matches_stripe']) {
                $hasMismatch = true;
            }
        }

        $this->newLine();
        $this->info("Recent transactions with platform_fee (limit {$limit})");

        $recent = StripeTransaction::query()
            ->with('customer')
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->when($currencyFilter !== null, fn ($q) => $q->where('currency', $currencyFilter))
            ->when($from !== null, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('created_at', '<=', $to))
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

        return $hasMismatch ? self::FAILURE : self::SUCCESS;
    }

    private function formatMoney(int $cents, string $currency): string
    {
        return sprintf('%s %.2f', strtoupper($currency), $cents / 100);
    }
}
