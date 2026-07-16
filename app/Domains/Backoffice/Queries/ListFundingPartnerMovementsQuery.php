<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListFundingPartnerMovementsQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = FundingPartnerMovement::query()
            ->with([
                'fundingAccount' => function ($morphTo) {
                    $morphTo->morphWith([
                        CustomerWallesterAccount::class => ['customer'],
                    ]);
                },
                'reference',
                'wallesterCard',
            ])
            ->latest('id');

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHasMorph(
                'fundingAccount',
                [CustomerWallesterAccount::class],
                fn ($accountQuery) => $accountQuery->where('customer_id', $customerId),
            );
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('partner_external_id', 'like', "%{$search}%")
                    ->orWhere('idempotency_key', 'like', "%{$search}%")
                    ->orWhere('external_account_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHasMorph(
                        'fundingAccount',
                        [CustomerWallesterAccount::class],
                        function ($accountQuery) use ($search) {
                            $accountQuery->whereHas('customer', function ($customerQuery) use ($search) {
                                $customerQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        },
                    );
            });
        }

        if ($type = $filters['movement_type'] ?? null) {
            $query->where('type', $type);
        }

        if ($status = $filters['movement_status'] ?? null) {
            $query->where('status', $status);
        }

        if ($direction = $filters['direction'] ?? null) {
            $query->where('direction', $direction);
        }

        if ($source = $filters['source'] ?? null) {
            $query->where('source', $source);
        }

        if ($from = $filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $filters['to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->paginate($perPage);
    }
}
