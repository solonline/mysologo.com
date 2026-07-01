<?php

namespace App\Domains\Wallester\Http\Middleware;

use App\Domains\Wallester\Services\WallesterSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWallester3dsWebhook
{
    public function __construct(
        private readonly WallesterSettingsService $wallesterSettings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $username = $this->wallesterSettings->threeDsWebhookUsername();
        $password = $this->wallesterSettings->threeDsWebhookPassword();

        if ($username === '' || $password === '') {
            abort(503, 'Wallester 3DS webhook credentials are not configured.');
        }

        $providedUsername = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        if (! hash_equals($username, $providedUsername) || ! hash_equals($password, $providedPassword)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($request->header('X-Request-ID') === null || $request->header('X-Request-ID') === '') {
            return response()->json(['error' => 'Missing X-Request-ID header'], 400);
        }

        return $next($request);
    }
}
