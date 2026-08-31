<?php

namespace App\Domains\Customer\Services;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use Illuminate\Support\Collection;

class CustomerFavoriteService
{
    public function add(Customer $owner, Customer $favorite): CustomerFavorite
    {
        return CustomerFavorite::query()->firstOrCreate([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);
    }

    /** @return Collection<int, Customer> */
    public function list(Customer $owner): Collection
    {
        return CustomerFavorite::query()
            ->where('customer_id', $owner->id)
            ->with('favoriteCustomer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CustomerFavorite $row) => $row->favoriteCustomer)
            ->filter(function (?Customer $customer): bool {
                if (! $customer) {
                    return false;
                }

                $status = $customer->status instanceof CustomerLifecycleStatus
                    ? $customer->status
                    : CustomerLifecycleStatus::tryFrom((string) $customer->status);

                $docStatus = strtolower((string) (
                    $customer->doc_status instanceof \BackedEnum
                        ? $customer->doc_status->value
                        : $customer->doc_status
                ));

                return $status === CustomerLifecycleStatus::ACTIVE
                    && $docStatus === strtolower(DocumentStatus::APPROVED->value);
            })
            ->values();
    }
}
