<?php

namespace App\Domains\Kyc;

final class DiditVendorData
{
    public static function forCustomer(int $customerId): string
    {
        return 'customer:'.$customerId;
    }

    public static function parseCustomerId(?string $vendorData): ?int
    {
        if (! is_string($vendorData) || $vendorData === '') {
            return null;
        }

        if (preg_match('/^customer:(\d+)$/', $vendorData, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
