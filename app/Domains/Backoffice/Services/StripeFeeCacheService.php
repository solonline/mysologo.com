<?php

namespace App\Domains\Backoffice\Services;

use Illuminate\Support\Facades\Cache;

class StripeFeeCacheService
{
    public function invalidateForCurrency(string $currency): void
    {
        $currency = strtoupper($currency);

        Cache::forget("payment_methods:{$currency}");
        Cache::forget("fee_estimates:{$currency}");
    }
}
