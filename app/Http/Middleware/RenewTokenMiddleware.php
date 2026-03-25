<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Carbon;

class RenewTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->expires_at && Carbon::parse($accessToken->expires_at)->isFuture()) {

                $accessToken->expires_at = now()->addMinutes(2);
                $accessToken->save();
            }
        }
        return $response;
    }
}
