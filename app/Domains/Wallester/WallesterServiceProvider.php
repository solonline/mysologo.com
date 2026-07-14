<?php

namespace App\Domains\Wallester;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Commands\SyncWallesterCardMovementsCommand;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Gateways\WallesterGateway;
use App\Domains\Wallester\Repositories\WallesterAccountRepository;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use App\Domains\Wallester\Services\SyncWallesterCardMovementsService;
use App\Domains\Wallester\Services\Wallester3dsService;
use App\Domains\Wallester\Services\WallesterCardMovementIngestService;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterService;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Domains\Wallester\Services\WallesterTransactionService;
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
        $this->app->singleton(Wallester3dsService::class);
        $this->app->singleton(WallesterSettingsService::class);
        $this->app->singleton(WallesterTransactionService::class);
        $this->app->singleton(WallesterFundingTransferDriver::class);
        $this->app->singleton(PlatformMasterCurrencyResolver::class);
        $this->app->singleton(WallesterCardMovementIngestService::class);
        $this->app->bind(SyncWallesterCardMovementsService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncWallesterCardMovementsCommand::class,
            ]);
        }

        Route::middleware('api')
            ->prefix('api/wallester')
            ->group(__DIR__.'/Routes/wallester.php');
    }
}
