<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Models\Customer;
use Illuminate\Support\Collection;

class SearchCustomersByUsernameQuery
{
    /**
     * @return Collection<int, array{id: int, username: string, name: string}>
     */
    public function execute(string $query, ?int $excludeId = null, int $limit = 8): Collection
    {
        $term = ltrim(trim($query), '@');

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Customer::query()
            ->where('type', '!=', 'platform')
            ->when($excludeId, fn ($builder) => $builder->where('id', '!=', $excludeId))
            ->where('username', 'like', $like)
            ->whereHas(
                'wallesterAccount',
                fn ($builder) => $builder->where('status', IntegrationAccountStatus::ACTIVE),
            )
            ->orderBy('username')
            ->limit($limit)
            ->get(['id', 'username', 'name'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'username' => (string) $customer->username,
                'name' => (string) ($customer->name ?? ''),
            ])
            ->values();
    }
}
