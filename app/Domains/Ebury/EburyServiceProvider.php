<?php

namespace App\Domains\Ebury;

use App\Domains\Ebury\Contracts\EburyGatewayInterface;
use App\Domains\Ebury\Gateways\SimulatedEburyGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EburyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EburyGatewayInterface::class, function () {
            if (config('services.ebury.simulate', true)) {
                return $this->app->make(SimulatedEburyGateway::class);
            }

            return $this->app->make(SimulatedEburyGateway::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/ebury')
            ->group(__DIR__.'/Routes/ebury.php');
    }
}
