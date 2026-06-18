<?php

namespace App\Domains\Wallester;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Gateways\WallesterGateway;
use App\Domains\Wallester\Repositories\WallesterAccountRepository;
use App\Domains\Wallester\Services\WallesterService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WallesterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WallesterHttpClient::class);
        $this->app->bind(WallesterGatewayInterface::class, WallesterGateway::class);
        $this->app->bind(WallesterAccountRepositoryInterface::class, WallesterAccountRepository::class);
        $this->app->singleton(WallesterService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v2/wallester')
            ->group(__DIR__ . '/Routes/wallester.php');
    }
}
