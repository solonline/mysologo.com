<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class RewardProgramListResult
{
    public function __construct(
        public LengthAwarePaginator $items,
    ) {}
}
