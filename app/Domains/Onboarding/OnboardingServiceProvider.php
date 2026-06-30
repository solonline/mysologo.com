<?php

namespace App\Domains\Onboarding;

use App\Domains\Onboarding\Contracts\PhoneVerificationRepositoryInterface;
use App\Domains\Onboarding\Repositories\PhoneVerificationRepository;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Onboarding\Services\OnboardingStatusResolver;
use App\Domains\Onboarding\Services\PhoneVerificationService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PhoneVerificationRepositoryInterface::class, PhoneVerificationRepository::class);
        $this->app->singleton(FinancialProvisioningService::class);
        $this->app->singleton(IntegrationAccountService::class);
        $this->app->singleton(CustomerLifecycleService::class);
        $this->app->singleton(OnboardingStatusResolver::class);
        $this->app->singleton(PhoneVerificationService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/onboarding')
            ->group(__DIR__.'/Routes/onboarding.php');
    }
}
