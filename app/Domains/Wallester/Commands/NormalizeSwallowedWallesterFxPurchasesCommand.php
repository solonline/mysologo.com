<?php

declare(strict_types=1);

namespace App\Domains\Wallester\Commands;

use App\Domains\Wallester\Services\NormalizeSwallowedWallesterFxPurchasesService;
use Illuminate\Console\Command;

class NormalizeSwallowedWallesterFxPurchasesCommand extends Command
{
    protected $signature = 'wallester:normalize-fx-fee-purchases
                            {--customer= : ID do cliente (customers.id). Sem isto, percorre todos.}';

    protected $description = 'Converte movimentos que gravaram só a taxa FX (e anexaram a compra como settlement) no movimento unificado compra+câmbio.';

    public function handle(NormalizeSwallowedWallesterFxPurchasesService $normalizer): int
    {
        $customerId = $this->option('customer') !== null ? (int) $this->option('customer') : null;

        $this->info('wallester:normalize-fx-fee-purchases — a unificar compras com câmbio...');

        $normalized = $normalizer->execute($customerId);

        foreach ($normalized as $movement) {
            $this->line(sprintf(
                '#%d amount=%d %s fees=%s original=%s %s partner=%s %s',
                $movement->id,
                $movement->amount,
                $movement->currency,
                $movement->fees_amount ?? '—',
                $movement->original_amount ?? '—',
                $movement->original_currency ?? '',
                $movement->partner_external_id,
                $movement->description,
            ));
        }

        $this->info(sprintf('Concluído — %d movimento(s) normalizado(s).', $normalized->count()));

        return self::SUCCESS;
    }
}
