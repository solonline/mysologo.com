<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class StripeFeeListResult
{
    /**
     * @param  list<string>  $currencies
     */
    public function __construct(
        public LengthAwarePaginator $fees,
        public array $currencies,
    ) {}
}
