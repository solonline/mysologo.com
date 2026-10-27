<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\AlignPlatformWalletToWallesterService;
use Illuminate\Console\Command;

class AlignPlatformWalletToWallesterCommand extends Command
{
    protected $signature = 'wallet:align-platform-to-wallester
                            {--dry-run : Mostra o delta sem gravar}';

    protected $description = 'Alinha o ledger e o wise_allocated da conta master ao disponível Wallester.';

    public function handle(AlignPlatformWalletToWallesterService $aligner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'wallet:align-platform-to-wallester — dry-run'
            : 'wallet:align-platform-to-wallester — a alinhar ledger e Wise à master Wallester...');

        $results = $aligner->execute($dryRun);

        if ($results === []) {
            $this->warn('Conta master Wallester não encontrada.');

            return self::SUCCESS;
        }

        $this->table(
            ['Moeda', 'Ledger', 'Wise', 'Wallester', 'Δ ledger', 'Δ wise', 'Sync API', 'Gravado'],
            array_map(function (array $row) use ($dryRun): array {
                $written = match (true) {
                    $dryRun => 'dry-run',
                    $row['applied'] || $row['wise_applied'] => 'sim',
                    default => 'já igual',
                };

                return [
                    $row['currency'],
                    number_format($row['from_cents'] / 100, 2, ',', '.'),
                    number_format($row['from_wise_cents'] / 100, 2, ',', '.'),
                    number_format($row['to_cents'] / 100, 2, ',', '.'),
                    number_format($row['delta_cents'] / 100, 2, ',', '.'),
                    number_format($row['wise_delta_cents'] / 100, 2, ',', '.'),
                    $row['synced'] ? 'sim' : 'falhou (usou cache)',
                    $written,
                ];
            }, $results),
        );

        return self::SUCCESS;
    }
}
