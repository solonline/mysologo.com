<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\BackfillStripePlatformFeesToSubMasterService;
use Illuminate\Console\Command;

class BackfillStripePlatformFeesToSubMasterCommand extends Command
{
    protected $signature = 'wallet:backfill-stripe-fees-to-sub-master
                            {--dry-run : Só mostra o relatório, sem gravar nem transferir}';

    protected $description = 'Cria as rows de taxa Stripe na Sub Conta Master e transfere o que a liquidez da master permitir.';

    public function handle(BackfillStripePlatformFeesToSubMasterService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'wallet:backfill-stripe-fees-to-sub-master — dry-run'
            : 'wallet:backfill-stripe-fees-to-sub-master — a criar rows e a transferir o possível...');

        $result = $service->execute($dryRun);

        $this->table(
            ['Resumo', 'EUR'],
            [
                ['Já na sub-conta (câmbio)', number_format($result['fx_already_cents'] / 100, 2, ',', '.')],
                ['Cai na sub-conta agora (Stripe)', number_format($result['landed_cents'] / 100, 2, ',', '.')],
                ['Pendente de liquidez (master)', number_format($result['pending_liquidity_cents'] / 100, 2, ',', '.')],
                ['Pendente Stripe (payout/saldo)', number_format($result['pending_stripe_cents'] / 100, 2, ',', '.')],
                ['Disponível na master', number_format($result['master_available_cents'] / 100, 2, ',', '.')],
                ['Falta na master para o pendente liquidez', number_format($result['liquidity_shortfall_cents'] / 100, 2, ',', '.')],
            ],
        );

        $this->table(
            ['Tx', 'Tipo', 'Cliente', 'Taxa', 'Funding', 'Destino'],
            array_map(fn (array $row) => [
                '#'.$row['transaction_id'],
                $row['type'],
                $row['customer'] ?? '—',
                number_format($row['fee_cents'] / 100, 2, ',', '.'),
                $row['funding_status'] ?? '—',
                $row['status'],
            ], $result['rows']),
        );

        return self::SUCCESS;
    }
}
