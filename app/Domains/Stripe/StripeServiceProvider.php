<?php

namespace App\Domains\Stripe;

use App\Domains\Stripe\Commands\SettleStripeBalances;
use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Domains\Stripe\Contracts\StripeAccountRepositoryInterface;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Gateways\StripeGateway;
use App\Domains\Stripe\Listeners\CreateStripeCustomer;
use App\Domains\Stripe\Repositories\StripeAccountRepository;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Onboarding\Events\CustomerApproved;
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SettleStripeBalances::class,
            ]);
        }

        Event::listen(CustomerApproved::class, CreateStripeCustomer::class);

        Route::middleware('api')
            ->prefix('api/v2/stripe')
            ->group(__DIR__ . '/Routes/stripe.php');
    }
}
