<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;

class MaintenanceModeMiddleware
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->settings->getByKey('system.maintenance_mode') === 'true') {
            throw new ApiException(
                ErrorCode::MAINTENANCE_MODE,
                503,
                'Service temporarily unavailable. Please try again later.'
            );
        }

        return $next($request);
    }
}
