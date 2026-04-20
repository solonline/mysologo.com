<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;

class EnsureCustomerIsActiveMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $customer = $request->user();

        if (!$customer) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                401,
                'Unauthenticated user.'
            );
        }

        $status = strtolower((string) $this->normalizeValue($customer->status));

        if ($status !== 'active') {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                403,
                'Account is not active.'
            );
        }

        return $next($request);
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
