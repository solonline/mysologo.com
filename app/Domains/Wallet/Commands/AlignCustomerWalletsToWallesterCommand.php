<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\AlignCustomerWalletsToWallesterService;
use Illuminate\Console\Command;

class AlignCustomerWalletsToWallesterCommand extends Command
{
    protected $signature = 'wallet:align-customers-to-wallester
                            {--dry-run : Mostra o delta sem gravar}';

    protected $description = 'Alinha o ledger e o wise_allocated de cada cliente ao disponível Wallester.';

    public function handle(AlignCustomerWalletsToWallesterService $aligner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'wallet:align-customers-to-wallester — dry-run'
            : 'wallet:align-customers-to-wallester — a alinhar ledger e Wise ao disponível Wallester...');

        $results = $aligner->execute($dryRun);

        if ($results === []) {
            $this->warn('Nenhuma conta Wallester encontrada.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cliente', 'Moeda', 'Ledger', 'Wise', 'Wallester', 'Δ ledger', 'Δ wise', 'Sync', 'Gravado'],
            array_map(function (array $row) use ($dryRun): array {
                $written = match (true) {
                    ! $row['synced'] => 'não',
                    $dryRun => 'dry-run',
                    $row['applied'] || $row['wise_applied'] => 'sim',
                    default => 'já igual',
                };

                return [
                    $row['customer_id'].' '.$row['customer_name'],
                    $row['currency'],
                    number_format($row['from_cents'] / 100, 2, ',', '.'),
                    number_format($row['from_wise_cents'] / 100, 2, ',', '.'),
                    $row['to_cents'] === null ? '—' : number_format($row['to_cents'] / 100, 2, ',', '.'),
                    $row['synced'] ? number_format($row['delta_cents'] / 100, 2, ',', '.') : '—',
                    $row['synced'] ? number_format($row['wise_delta_cents'] / 100, 2, ',', '.') : '—',
                    $row['synced'] ? 'sim' : 'falhou',
                    $written,
                ];
            }, $results),
        );

        return self::SUCCESS;
    }
}
