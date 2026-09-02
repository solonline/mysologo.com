<?php

namespace App\Domains\Fx;

use App\Domains\Fx\Clients\AwesomeApiHttpClient;
use App\Domains\Fx\Contracts\FxClientInterface;
use App\Domains\Fx\Contracts\FxGatewayInterface;
use App\Domains\Fx\Gateways\FxGateway;
use App\Domains\Fx\Services\FxQuoteService;
use App\Domains\Fx\Services\FxSettingsService;
use Illuminate\Support\ServiceProvider;

class FxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FxSettingsService::class);
        $this->app->bind(FxClientInterface::class, AwesomeApiHttpClient::class);
        $this->app->bind(FxGatewayInterface::class, FxGateway::class);
        $this->app->singleton(FxQuoteService::class);
    }
}
