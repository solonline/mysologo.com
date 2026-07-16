<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Collection;

class GetRecentCustomerMovementsQuery
{
    /**
     * @return Collection<int, array{source: string, occurred_at: mixed, movement: mixed}>
     */
    public function execute(Customer $customer, int $limit = 10): Collection
    {
        $stripeTransactions = $customer->stripeTransactions()
            ->latest('stripe_created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($transaction): array => [
                'source' => 'stripe',
                'occurred_at' => $transaction->stripe_created_at ?? $transaction->created_at,
                'movement' => $transaction,
            ]);

        $wallesterMovements = FundingPartnerMovement::query()
            ->with('wallesterCard')
            ->whereHasMorph(
                'fundingAccount',
                [CustomerWallesterAccount::class],
                fn ($query) => $query->where('customer_id', $customer->id),
            )
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($movement): array => [
                'source' => 'wallester',
                'occurred_at' => $movement->created_at,
                'movement' => $movement,
            ]);

        return $stripeTransactions
            ->concat($wallesterMovements)
            ->sortByDesc(fn (array $item) => $item['occurred_at']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }
}
