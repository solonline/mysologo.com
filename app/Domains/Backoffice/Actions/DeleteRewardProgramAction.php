<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\RewardProgram;
use RuntimeException;

class DeleteRewardProgramAction
{
    public function execute(RewardProgram $program): void
    {
        if ($program->isReferral()) {
            throw new RuntimeException('O programa de indicação não pode ser removido.');
        }

        if ($program->rewards()->exists()) {
            throw new RuntimeException('Não é possível remover um programa com rewards geradas.');
        }

        $program->delete();
    }
}
