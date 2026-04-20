<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;

class EnsureCustomerDocumentApprovedMiddleware
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

        $documentStatus = strtolower((string) $this->normalizeValue($customer->doc_status));

        if ($documentStatus !== 'approved') {
            throw new ApiException(
                ErrorCode::DOCUMENT_NOT_APPROVED,
                403,
                'Customer document is not approved.'
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
