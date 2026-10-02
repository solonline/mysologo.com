<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\Reward;
use App\Models\RewardProgram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRewardProgramRewardsQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(RewardProgram $program, array $filters): LengthAwarePaginator
    {
        $query = Reward::query()
            ->where('program_id', $program->id)
            ->with(['customer', 'referred']);

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($inner) use ($search) {
                $inner->whereHas('customer', function ($customer) use ($search) {
                    $customer->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('referred', function ($referred) use ($search) {
                    $referred->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            });
        }

        return $query->latest()->paginate(20);
    }
}
