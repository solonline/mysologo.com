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
                $user = $accessToken->tokenable;
                // Define tempo conforme role

                match (true) {
                    $user->hasRole('admin') => $minutes = 120,
                    $user->hasRole('editor') => $minutes = 60,
                    default => $minutes = (new SettingsService())->get('default_token_expiration'),
                };

                $accessToken->expires_at = now()->addMinutes($minutes);
                $accessToken->save();
            }
        }
        return $response;
    }
}
