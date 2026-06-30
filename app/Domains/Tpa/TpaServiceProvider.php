<?php

namespace App\Domains\Tpa;

use App\Domains\Tpa\Services\TpaLinkService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TpaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TpaLinkService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/tpa')
            ->group(__DIR__.'/Routes/tpa.php');
    }
}
