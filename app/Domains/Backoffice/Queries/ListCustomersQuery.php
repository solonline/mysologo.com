<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Backoffice\DTOs\CustomerListResult;

class ListCustomersQuery
{
    public function __construct(
        protected CustomerRepositoryInterface $customers,
    ) {}

    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): CustomerListResult
    {
        return new CustomerListResult(
            customers: $this->customers->paginate($filters),
            stats: $this->customers->getStats(),
        );
    }
}
