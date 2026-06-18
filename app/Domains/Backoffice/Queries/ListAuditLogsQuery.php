<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ListAuditLogsQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('subject_id', is_numeric($search) ? $search : null)
                    ->orWhereHasMorph('causer', [User::class, Customer::class],
                        fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($log = $filters['log'] ?? null) {
            $query->inLog($log);
        }

        if ($event = $filters['event'] ?? null) {
            $query->where('event', $event);
        }

        if ($from = $filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $filters['to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->latest()->paginate(25);
    }
}
