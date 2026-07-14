<?php

declare(strict_types=1);

namespace App\Domains\Wallester\Commands;

use App\Domains\Wallester\Services\SyncWallesterCardMovementsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncWallesterCardMovementsCommand extends Command
{
    protected $signature = 'wallester:sync-card-movements
                            {--card= : ID local do cartão (customer_wallester_cards.id)}
                            {--hours=13 : Janela de lookback em horas (default: 13, overlap para schedule de 12h)}
                            {--from= : Data inicial (Y-m-d\\TH:i:s\\Z). Sobrescreve --hours}
                            {--to= : Data final (Y-m-d\\TH:i:s\\Z). Default: agora UTC}';

    protected $description = 'Sincroniza transacções de cartões Wallester activos e regista movimentos em falta (fallback ao webhook).';

    public function handle(SyncWallesterCardMovementsService $syncService): int
    {
        $cardId = $this->option('card') !== null ? (int) $this->option('card') : null;
        $hours = max(1, (int) $this->option('hours'));

        $to = $this->option('to') !== null
            ? Carbon::createFromFormat('Y-m-d\TH:i:s\Z', (string) $this->option('to'), 'UTC')
            : null;

        $from = $this->option('from') !== null
            ? Carbon::createFromFormat('Y-m-d\TH:i:s\Z', (string) $this->option('from'), 'UTC')
            : null;

        $this->info('wallester:sync-card-movements — a sincronizar transacções de cartões activos...');

        $stats = $syncService->run(
            cardId: $cardId,
            from: $from,
            to: $to,
            lookbackHours: $hours,
        );

        $this->info(sprintf(
            'Concluído — %d cartão(ões), %d transacção(ões) processada(s), %d falha(s).',
            $stats['cards_scanned'],
            $stats['transactions_fetched'],
            $stats['failures'],
        ));

        return $stats['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
