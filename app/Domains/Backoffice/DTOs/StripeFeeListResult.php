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

readonly class StripeFeeFormData
{
    /**
     * @param  list<string>  $currencies
     * @param  array<string, mixed>  $stripeAvailability
     */
    public function __construct(
        public array $currencies,
        public array $stripeAvailability,
    ) {}
}
