<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\AlignCustomerWalletsToWallesterService;
use Illuminate\Console\Command;

class AlignCustomerWalletsToWallesterCommand extends Command
{
    protected $signature = 'wallet:align-customers-to-wallester
                            {--dry-run : Mostra o delta sem gravar}
                            {--mirror : Fecha pendente sem payout Stripe e transferências Wise cativas, e iguala os livros ao disponível Wallester}';

    protected $description = 'Alinha o ledger e o wise_allocated de cada cliente ao disponível Wallester.';

    public function handle(AlignCustomerWalletsToWallesterService $aligner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mirror = (bool) $this->option('mirror');

        $this->info($dryRun
            ? 'wallet:align-customers-to-wallester — dry-run'.($mirror ? ' --mirror' : '')
            : 'wallet:align-customers-to-wallester — a alinhar ledger e Wise ao disponível Wallester'.($mirror ? ' (mirror)' : '').'...');

        $results = $mirror ? $aligner->mirror($dryRun) : $aligner->execute($dryRun);

        if ($results === []) {
            $this->warn('Nenhuma conta Wallester encontrada.');

            return self::SUCCESS;
        }

        $headers = ['Cliente', 'Moeda', 'Ledger', 'Wise', 'Wallester', 'Δ ledger', 'Δ wise', 'Sync', 'Gravado'];

        if ($mirror) {
            array_splice($headers, 7, 0, ['Pendente', 'Cativas']);
        }

        $this->table(
            $headers,
            array_map(function (array $row) use ($dryRun, $mirror): array {
                $written = match (true) {
                    ! $row['synced'] => 'não',
                    $dryRun => 'dry-run',
                    $row['applied'] || $row['wise_applied'] || $row['pending_reversed_cents'] > 0 || $row['transfers_closed'] > 0 => 'sim',
                    default => 'já igual',
                };

                $line = [
                    $row['customer_id'].' '.$row['customer_name'],
                    $row['currency'],
                    number_format($row['from_cents'] / 100, 2, ',', '.'),
                    number_format($row['from_wise_cents'] / 100, 2, ',', '.'),
                    $row['to_cents'] === null ? '—' : number_format($row['to_cents'] / 100, 2, ',', '.'),
                    $row['synced'] ? number_format($row['delta_cents'] / 100, 2, ',', '.') : '—',
                    $row['synced'] ? number_format($row['wise_delta_cents'] / 100, 2, ',', '.') : '—',
                ];

                if ($mirror) {
                    $line[] = $row['synced'] ? number_format($row['pending_reversed_cents'] / 100, 2, ',', '.') : '—';
                    $line[] = $row['synced'] ? (string) $row['transfers_closed'] : '—';
                }

                $line[] = $row['synced'] ? 'sim' : 'falhou';
                $line[] = $written;

                return $line;
            }, $results),
        );

        return self::SUCCESS;
    }
}
