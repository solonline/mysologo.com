<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Wallet\Services\SettlementService;
use Illuminate\Console\Command;

class SettleStripeBalances extends Command
{
    protected $signature = 'stripe:settle-balances {--date= : Data de liquidação (Y-m-d). Padrão: hoje}';

    protected $description = 'Fallback/retry de funding Wallester após payout.paid (não credita por available_on).';

    public function __construct(private readonly SettlementService $settlementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? now()->parse($this->option('date'))
            : today();

        $this->info("stripe:settle-balances — fallback/retry para {$date->toDateString()}...");

        $result = $this->settlementService->runDailySettlement($date);

        $this->info(sprintf(
            'Concluído — retried=%d skipped=%d awaiting_payout_stale=%d',
            $result['retried'] ?? 0,
            $result['skipped'] ?? 0,
            $result['awaiting_payout_stale'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
