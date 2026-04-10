<?php

namespace App\Providers;

use App\Events\CustomerApproved;
use App\Listeners\CreateStripeCustomer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\SettingsRepositoryInterface::class, function ($app) {
            return new \App\Repositories\SettingRepository();
        });

        $this->app->bind(\App\Clients\DIditClient::class, function ($app) {
            return new \App\Clients\DIditClient();
        });
        $this->app->bind(\App\Providers\DiditProvider::class, function ($app) {
            return new \App\Providers\DiditProvider($app->make(\App\Clients\DIditClient::class));
        });
        $this->app->bind(\App\Contracts\DiditSessionRepositoryInterface::class, function ($app) {
            return new \App\Repositories\DiditSessionRepository();
        });

        $this->app->bind(\App\Contracts\RetailDocumentRepositoryInterface::class, function ($app) {
            return new \App\Repositories\RetailDocumentRepository();
        });

        $this->app->bind(\App\Contracts\CorporateDocumentRepositoryInterface::class, function ($app) {
            return new \App\Repositories\CorporateDocumentRepository();
        });

        $this->app->bind(\App\Services\DiditService::class, function ($app) {
            return new \App\Services\DiditService(
                $app->make(\App\Providers\DiditProvider::class),
                $app->make(\App\Contracts\DiditSessionRepositoryInterface::class),
                $app->make(\App\Contracts\RetailDocumentRepositoryInterface::class),
                $app->make(\App\Contracts\CustomerRepositoryInterface::class),
            );
        });

        // Bind para KycService
        $this->app->bind(\App\Services\KycService::class, function ($app) {
            return new \App\Services\KycService(
                $app->make(\App\Services\DiditService::class),
                $app->make(\App\Contracts\DiditSessionRepositoryInterface::class),
                $app->make(\App\Contracts\CustomerRepositoryInterface::class),
                $app->make(\App\Contracts\CorporateDocumentRepositoryInterface::class),
            );
        });

        // Bind para RetailCustomerService
        $this->app->bind(\App\Services\RetailCustomerService::class, function ($app) {
            return new \App\Services\RetailCustomerService();
        });

        $this->app->bind(\App\Contracts\CustomerRepositoryInterface::class, function ($app) {
            return new \App\Repositories\CustomerRepository();
        });

        $this->app->bind(\App\Contracts\StripeAccountRepositoryInterface::class, function ($app) {
            return new \App\Repositories\StripeAccountRepository();
        });

        $this->app->bind(\App\Contracts\DeviceRepositoryInterface::class, function ($app) {
            return new \App\Repositories\DeviceRepository();
        });

        $this->app->bind(\App\Services\DeviceAuthService::class, function ($app) {
            return new \App\Services\DeviceAuthService($app->make(\App\Contracts\DeviceRepositoryInterface::class));
        });

        // Stripe
        $this->app->bind(\App\Clients\StripeClient::class, function ($app) {
            return new \App\Clients\StripeClient($app->make(\App\Services\SettingsService::class));
        });
        $this->app->bind(\App\Providers\StripeProvider::class, function ($app) {
            return new \App\Providers\StripeProvider($app->make(\App\Clients\StripeClient::class));
        });
        $this->app->bind(\App\Services\StripeService::class, function ($app) {
            return new \App\Services\StripeService($app->make(\App\Providers\StripeProvider::class));
        });

        $this->app->bind(\App\Contracts\TransactionRepositoryInterface::class, function ($app) {
            return new \App\Repositories\TransactionRepository();
        });

        $this->app->bind(\App\Services\DashboardService::class, function ($app) {
            return new \App\Services\DashboardService(
                $app->make(\App\Contracts\TransactionRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CustomerApproved::class, CreateStripeCustomer::class);

        // Define o rate limit para o grupo 'api'.
        // Se o usuário estiver autenticado, limita por ID do usuário + IP.
        // Se não estiver autenticado, limita apenas por IP.
        // Isso evita que múltiplos usuários na mesma rede (mesmo IP) sejam bloqueados pelo limite global.
        \Illuminate\Support\Facades\RateLimiter::for('api', function ($request) {
            if ($request->user()) {
                // Limite de 60 requisições por minuto por usuário + IP
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()->id . '|' . $request->ip());
            }
            // Limite de 60 requisições por minuto por IP para não autenticados
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });

        // Configura o header Bearer global para a documentação Scramble (OpenAPI)
        // Isso garante que a documentação da API mostre o uso de autenticação Bearer
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        // Filtra as rotas que serão documentadas pelo Scramble
        // Só documenta rotas que começam com 'api/' e exclui rotas admin e webhook
        Scramble::routes(function (Route $route) {
            $uri = $route->uri();
            if (!Str::startsWith($uri, 'api/')) {
                return false;
            }
            if (Str::startsWith($uri, 'api/admin') || $uri === 'api/webhooks/didit') {
                return false;
            }
            return true;
        });
    }
}
