<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class CustomerListResult
{
    public function __construct(
        public LengthAwarePaginator $customers,
        /** @var array<string, int> */
        public array $stats,
    ) {}
}
