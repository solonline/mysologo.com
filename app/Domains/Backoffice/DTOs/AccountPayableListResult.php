<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class AccountPayableListResult
{
    /**
     * @param  Collection<int, string>  $categories
     * @param  list<string>  $currencies
     * @param  list<string>  $statuses
     */
    public function __construct(
        public LengthAwarePaginator $accounts,
        public Collection $categories,
        public array $currencies,
        public array $statuses,
    ) {}
}
