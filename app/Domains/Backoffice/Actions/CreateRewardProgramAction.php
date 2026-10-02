<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\RewardProgramData;
use App\Models\RewardProgram;

class CreateRewardProgramAction
{
    public function execute(RewardProgramData $data): RewardProgram
    {
        return RewardProgram::query()->create($data->toAttributes());
    }
}
