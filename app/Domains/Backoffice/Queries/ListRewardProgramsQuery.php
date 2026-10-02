<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\RewardProgramListResult;
use App\Models\RewardProgram;

class ListRewardProgramsQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): RewardProgramListResult
    {
        $query = RewardProgram::query()->withCount('rewards');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        return new RewardProgramListResult(
            items: $query->orderBy('name')->paginate(20),
        );
    }
}
