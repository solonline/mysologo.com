<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\RewardProgramData;
use App\Models\RewardProgram;

class UpdateRewardProgramAction
{
    public function execute(RewardProgram $program, RewardProgramData $data): RewardProgram
    {
        $attributes = $data->toAttributes();
        unset($attributes['slug']);

        $program->update($attributes);

        return $program->fresh() ?? $program;
    }
}
