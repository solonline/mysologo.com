<?php

namespace App\Domains\Wallester\Services;

class PlatformMasterCurrencyResolver
{
    public function resolve(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => 'USD',
            'EUR' => 'EUR',
            default => 'EUR',
        };
    }
}
