<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\BackfillFxPlatformFeeAccountLedgerService;
use Illuminate\Console\Command;

class BackfillFxPlatformFeeAccountLedgerCommand extends Command
{
    protected $signature = 'wallet:backfill-fx-fee-account-ledger
                            {--currency=EUR : Moeda da subconta}
                            {--dry-run : Só mostra o relatório, sem gravar}';

    protected $description = 'Grava no ledger da subconta (fee account) os ganhos/reversões Câmbio Yoop em falta e realinha o saldo à Wallester.';

    public function handle(BackfillFxPlatformFeeAccountLedgerService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $currency = (string) $this->option('currency');

        $this->info($dryRun
            ? 'wallet:backfill-fx-fee-account-ledger — dry-run'
            : 'wallet:backfill-fx-fee-account-ledger — a gravar ledger...');

        $result = $service->execute($currency, $dryRun);

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Moeda', $result['currency']],
                ['Subconta Wallester', (string) ($result['fee_account_id'] ?? '—')],
                ['Créditos (ganhos)', (string) $result['credited'].' / '.number_format($result['credited_cents'] / 100, 2, ',', '.').' €'],
                ['Débitos (reversões)', (string) $result['debited'].' / '.number_format($result['debited_cents'] / 100, 2, ',', '.').' €'],
                ['Já tinham ledger', (string) $result['skipped']],
                ['Saldo ledger antes→depois', number_format($result['from_cents'] / 100, 2, ',', '.').' → '.number_format($result['to_cents'] / 100, 2, ',', '.')],
                ['Delta realinhamento', number_format($result['delta_cents'] / 100, 2, ',', '.').' €'.($result['realigned'] ? ' (aplicado)' : ($dryRun && $result['delta_cents'] !== 0 ? ' (dry-run)' : ''))],
            ],
        );

        return self::SUCCESS;
    }
}
