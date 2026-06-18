<?php

namespace App\Http\Middleware;

use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;

class EnsureCustomerIsActiveMiddleware
{
    public function __construct(
        protected IntegrationAccountService $integrations,
    ) {}

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

        if (! $this->integrations->canTransact($customer)) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                403,
                'Account is not active.'
            );
        }

        return $next($request);
    }
}
