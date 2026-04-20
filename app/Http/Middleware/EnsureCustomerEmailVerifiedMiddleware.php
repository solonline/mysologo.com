<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;

class EnsureCustomerEmailVerifiedMiddleware
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

        if (!$customer->hasVerifiedEmail()) {
            throw new ApiException(
                ErrorCode::EMAIL_NOT_VERIFIED,
                403,
                'Email address is not verified.'
            );
        }

        return $next($request);
    }
}
