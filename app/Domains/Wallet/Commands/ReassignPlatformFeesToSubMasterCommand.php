<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\ReassignPlatformFeesToSubMasterService;
use Illuminate\Console\Command;

class ReassignPlatformFeesToSubMasterCommand extends Command
{
    protected $signature = 'wallet:reassign-platform-fees-to-sub-master
                            {--dry-run : Mostra o total sem transferir nem gravar}';

    protected $description = 'Move o lucro já cobrado (câmbio/taxas) da master para a Sub Conta Master, reatribui as rows e atualiza saldos. Sem movimento 1→2.';

    public function handle(ReassignPlatformFeesToSubMasterService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'wallet:reassign-platform-fees-to-sub-master — dry-run'
            : 'wallet:reassign-platform-fees-to-sub-master — a transferir e reatribuir...');

        $result = $service->execute($dryRun);

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Rows IN', (string) $result['rows']],
                ['Montante', number_format($result['amount_cents'] / 100, 2, ',', '.').' '.$result['currency']],
                ['De (master)', $result['master_account_id']],
                ['Para (sub)', $result['fee_account_id']],
                ['Transferência Wallester', $result['transferred'] ? 'sim' : ($dryRun ? 'dry-run' : 'não')],
            ],
        );

        return self::SUCCESS;
    }
}
