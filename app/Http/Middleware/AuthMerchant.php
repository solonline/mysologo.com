<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMerchant
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Merchant-Key') ?: $request->header('X-Api-Key');

        if (! $rawKey) {
            return response()->json(['message' => 'API key em falta.'], 401);
        }

        $merchant = Merchant::findByRawKey($rawKey);

        if (! $merchant) {
            return response()->json(['message' => 'API key inválida ou merchant inactivo.'], 401);
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
