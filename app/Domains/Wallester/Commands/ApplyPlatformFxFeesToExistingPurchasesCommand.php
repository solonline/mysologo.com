<?php

declare(strict_types=1);

namespace App\Domains\Wallester\Commands;

use App\Domains\Wallester\Services\ApplyPlatformFxFeesToExistingPurchasesService;
use Illuminate\Console\Command;

class ApplyPlatformFxFeesToExistingPurchasesCommand extends Command
{
    protected $signature = 'wallester:apply-platform-fx-fees
                            {--customer= : ID do cliente (customers.id). Sem isto, percorre todos.}';

    protected $description = 'Corrige compras FX antigas (amount só cotado + row Câmbio) e cobra a % Yoop para a master.';

    public function handle(ApplyPlatformFxFeesToExistingPurchasesService $applier): int
    {
        $customerId = $this->option('customer') !== null ? (int) $this->option('customer') : null;

        $this->info('wallester:apply-platform-fx-fees — a rever ledger e a aplicar taxa da plataforma...');

        $processed = $applier->execute($customerId);

        foreach ($processed as $movement) {
            $this->line(sprintf(
                '#%d amount=%d %s orig=%s %s wallester=%s yoop=%s partner=%s %s',
                $movement->id,
                $movement->amount,
                $movement->currency,
                $movement->original_amount ?? '—',
                $movement->original_currency ?? '',
                $movement->fees_amount ?? '—',
                $movement->platform_fees_amount ?? '—',
                $movement->partner_external_id,
                $movement->description,
            ));
        }

        $this->info(sprintf('Concluído — %d compra(s) FX processada(s).', $processed->count()));

        return self::SUCCESS;
    }
}
