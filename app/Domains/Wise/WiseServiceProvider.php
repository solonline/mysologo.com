<?php

namespace App\Domains\Wise;

use App\Domains\Wise\Clients\WiseHttpClient;
use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Domains\Wise\Gateways\WiseGateway;
use App\Domains\Wise\Services\WiseBeneficiaryService;
use App\Domains\Wise\Services\WiseSettingsService;
use App\Domains\Wise\Services\WiseTransferService;
use App\Domains\Wise\Services\WiseWebhookProcessor;
use App\Domains\Wise\Services\WiseWebhookSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WiseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WiseSettingsService::class);
        $this->app->singleton(WiseHttpClient::class);
        $this->app->singleton(WiseWebhookSignature::class);
        $this->app->singleton(WiseWebhookProcessor::class);
        $this->app->bind(WiseGatewayInterface::class, WiseGateway::class);
        $this->app->singleton(WiseBeneficiaryService::class);
        $this->app->singleton(WiseTransferService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/wise')
            ->group(__DIR__.'/Routes/wise.php');
    }
}
