<?php

namespace App\Domains\Kyc;

use App\Domains\Kyc\Clients\DiditHttpClient;
use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Domains\Kyc\Contracts\KycGatewayInterface;
use App\Domains\Kyc\Gateways\DiditKycGateway;
use App\Domains\Kyc\Repositories\IdentityVerificationSessionRepository;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KycServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DiditHttpClient::class);
        $this->app->bind(IdentityVerificationSessionRepositoryInterface::class, IdentityVerificationSessionRepository::class);
        $this->app->bind(KycGatewayInterface::class, DiditKycGateway::class);
        $this->app->singleton(IdentityVerificationService::class);
        $this->app->singleton(IdentityVerificationWebhookService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v2/kyc')
            ->group(__DIR__ . '/Routes/kyc.php');
    }
}
