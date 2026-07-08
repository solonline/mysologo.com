<?php

namespace App\Domains\Wallet\Contracts;

use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    public function getByCustomer(Customer $customer): Collection;

    public function getRecent(Customer $customer, int $limit = 10): Collection;

    public function getPaginated(Customer $customer, int $perPage = 15): LengthAwarePaginator;

    public function findForCustomer(Customer $customer, int $id): ?StripeTransaction;

    public function create(array $data): StripeTransaction;

    public function getAvailableBalance(int $customerId, string $currency): int;

    public function adjustBalance(int $customerId, string $currency, int $delta): void;
}
