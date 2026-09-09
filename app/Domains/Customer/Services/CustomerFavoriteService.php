<?php

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use Illuminate\Support\Collection;

class CustomerFavoriteService
{
    public function __construct(
        protected CustomerRepositoryInterface $customers,
    ) {}

    public function add(Customer $owner, Customer $favorite): CustomerFavorite
    {
        return CustomerFavorite::query()->firstOrCreate([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);
    }

    public function addByUsername(Customer $owner, string $username): Customer
    {
        $favorite = $this->resolveFavoriteCustomer($owner, $username, requireEligible: true);
        $this->add($owner, $favorite);

        return $favorite;
    }

    public function removeByUsername(Customer $owner, string $username): void
    {
        $favorite = $this->resolveFavoriteCustomer($owner, $username, requireEligible: false);

        CustomerFavorite::query()
            ->where('customer_id', $owner->id)
            ->where('favorite_customer_id', $favorite->id)
            ->delete();
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

    private function resolveFavoriteCustomer(Customer $owner, string $username, bool $requireEligible): Customer
    {
        if (strcasecmp((string) $owner->username, $username) === 0) {
            throw new ApiException(
                ErrorCode::FAVORITE_SELF,
                422,
                'Não é possível favoritar a própria conta.',
            );
        }

        $filters = $requireEligible
            ? [
                'status' => CustomerLifecycleStatus::ACTIVE,
                'doc_status' => DocumentStatus::APPROVED,
            ]
            : [];

        $customer = $this->customers->findByUsername($username, $filters, excludeId: $owner->id);

        if (! $customer) {
            throw new ApiException(
                ErrorCode::CUSTOMER_NOT_FOUND,
                404,
                'Customer not found.',
            );
        }

        return $customer;
    }
}
