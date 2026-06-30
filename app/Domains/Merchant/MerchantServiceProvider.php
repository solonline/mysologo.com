<?php

namespace App\Domains\Merchant;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\Contracts\MerchantRepositoryInterface;
use App\Domains\Merchant\Repositories\MerchantPaymentRequestRepository;
use App\Domains\Merchant\Repositories\MerchantRepository;
use App\Domains\Merchant\Services\MerchantService;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MerchantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MerchantRepositoryInterface::class, MerchantRepository::class);
        $this->app->bind(MerchantPaymentRequestRepositoryInterface::class, MerchantPaymentRequestRepository::class);

        $this->app->singleton(MerchantService::class, function ($app) {
            return new MerchantService(
                $app->make(MerchantRepositoryInterface::class),
                $app->make(MerchantPaymentRequestRepositoryInterface::class),
                $app->make(StripeGatewayInterface::class),
            );
        });
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/merchant')
            ->group(__DIR__.'/Routes/merchant.php');
    }
}
