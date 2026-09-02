<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\AlignPlatformWalletToWallesterService;
use Illuminate\Console\Command;

class AlignPlatformWalletToWallesterCommand extends Command
{
    protected $signature = 'wallet:align-platform-to-wallester
                            {--dry-run : Mostra o delta sem gravar}';

    protected $description = 'Alinha o livro interno da conta platform ao saldo da master Wallester.';

    public function handle(AlignPlatformWalletToWallesterService $aligner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'wallet:align-platform-to-wallester — dry-run'
            : 'wallet:align-platform-to-wallester — a alinhar o livro à caixa Wallester...');

        $results = $aligner->execute($dryRun);

        if ($results === []) {
            $this->warn('Conta platform ou master Wallester não encontrada.');

            return self::SUCCESS;
        }

        $this->table(
            ['Moeda', 'Livro', 'Wallester', 'Delta', 'Sync API', 'Gravado'],
            array_map(fn (array $row) => [
                $row['currency'],
                number_format($row['from_cents'] / 100, 2, ',', '.'),
                number_format($row['to_cents'] / 100, 2, ',', '.'),
                number_format($row['delta_cents'] / 100, 2, ',', '.'),
                $row['synced'] ? 'sim' : 'falhou (usou cache)',
                $row['applied'] ? 'sim' : ($dryRun ? 'dry-run' : 'já igual'),
            ], $results),
        );

        return self::SUCCESS;
    }
}
