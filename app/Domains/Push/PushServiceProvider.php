<?php

namespace App\Domains\Push;

use App\Domains\Push\Clients\FcmClient;
use App\Domains\Push\Commands\SendCustomerPushCommand;
use App\Domains\Push\Services\PushDeviceService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FcmClient::class);
        $this->app->singleton(PushDeviceService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendCustomerPushCommand::class,
            ]);
        }

        Route::middleware('api')
            ->prefix('api/push')
            ->group(__DIR__.'/Routes/push.php');
    }
}
