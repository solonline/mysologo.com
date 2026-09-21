<?php

declare(strict_types=1);

namespace App\Domains\Wallester\Commands;

use App\Domains\Wallester\Services\RepairAcceptedReversalsService;
use Illuminate\Console\Command;

class RepairAcceptedReversalsCommand extends Command
{
    protected $signature = 'wallester:repair-accepted-reversals
                            {--movement= : ID do funding_partner_movement a reparar}';

    protected $description = 'Reprocessa reversões AcceptedReversal gravadas como failed: credita o cliente e anula o câmbio proporcional.';

    public function handle(RepairAcceptedReversalsService $repair): int
    {
        $movementId = $this->option('movement') !== null ? (int) $this->option('movement') : null;

        $this->info('wallester:repair-accepted-reversals — a reprocessar reversões...');

        $repaired = $repair->execute($movementId);

        foreach ($repaired as $movement) {
            $this->line(sprintf(
                '#%d status=%s amount=%d %s partner=%s %s',
                $movement->id,
                is_object($movement->status) ? $movement->status->value : $movement->status,
                $movement->amount,
                $movement->currency,
                $movement->partner_external_id,
                $movement->description,
            ));
        }

        $this->info(sprintf('Concluído — %d reversão(ões) reparada(s).', $repaired->count()));

        return self::SUCCESS;
    }
}
