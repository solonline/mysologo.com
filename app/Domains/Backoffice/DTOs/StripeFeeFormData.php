<?php

namespace App\Domains\Backoffice\DTOs;

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
