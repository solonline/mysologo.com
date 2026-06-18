<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Wallet\Services\SettlementService;
use Illuminate\Console\Command;

class SettleStripeBalances extends Command
{
    protected $signature = 'stripe:settle-balances {--date= : Data de liquidação (Y-m-d). Padrão: hoje}';

    protected $description = 'Executa o pipeline de settlement: settle transações disponíveis, envia para conta master e distribui para IBANs individuais.';

    public function __construct(private readonly SettlementService $settlementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? now()->parse($this->option('date'))
            : today();

        $this->info("stripe:settle-balances — a processar liquidações para {$date->toDateString()}...");

        $result = $this->settlementService->runDailySettlement($date);

        $this->info("Concluído — {$result['batches']} batch(es), {$result['transactions']} transação(ões).");

        return self::SUCCESS;
    }
}
