<?php

namespace App\Domains\Stripe;

use App\Domains\Onboarding\Events\CustomerApproved;
use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Domains\Stripe\Commands\MigratePendingToPaymentIntents;
use App\Domains\Stripe\Commands\PurgeMerchantPaymentsCommand;
use App\Domains\Stripe\Commands\SettleStripeBalances;
use App\Domains\Stripe\Commands\SettleStripePayoutCommand;
use App\Domains\Stripe\Jobs\ExpireStaleStripePaymentIntentsJob;
use App\Domains\Stripe\Services\PurgeMerchantPaymentsService;
use App\Domains\Stripe\Services\StripePaymentIntentService;
use App\Domains\Stripe\Contracts\StripeAccountRepositoryInterface;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Gateways\StripeGateway;
use App\Domains\Stripe\Listeners\CreateStripeCustomer;
use App\Domains\Stripe\Repositories\StripeAccountRepository;
use App\Domains\Stripe\Services\StripeService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StripeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeHttpClient::class);
        $this->app->bind(StripeGatewayInterface::class, StripeGateway::class);
        $this->app->bind(StripeAccountRepositoryInterface::class, StripeAccountRepository::class);
        $this->app->singleton(StripeService::class);
        $this->app->singleton(StripePaymentIntentService::class);
        $this->app->singleton(PurgeMerchantPaymentsService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SettleStripeBalances::class,
                SettleStripePayoutCommand::class,
                MigratePendingToPaymentIntents::class,
                PurgeMerchantPaymentsCommand::class,
            ]);
        }

        Event::listen(CustomerApproved::class, CreateStripeCustomer::class);

        Route::middleware('api')
            ->prefix('api/stripe')
            ->group(__DIR__.'/Routes/stripe.php');
    }
}
