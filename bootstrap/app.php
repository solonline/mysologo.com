<?php
use App\Exceptions\ApiException;
// Middleware customizado para idempotência de requisições
use App\Http\Middleware\EnsureCustomerDocumentApprovedMiddleware;
use App\Http\Middleware\EnsureCustomerEmailVerifiedMiddleware;
use App\Http\Middleware\EnsureCustomerIsActiveMiddleware;
use App\Http\Middleware\IdempotencyRequiredMiddleware;
use App\Http\Middleware\MaintenanceModeMiddleware;
// Classe principal da aplicação Laravel
use Illuminate\Foundation\Application;
// Configuração de tratamento de exceções
use Illuminate\Foundation\Configuration\Exceptions;
// Configuração de middlewares globais e de grupos
use Illuminate\Foundation\Configuration\Middleware;
// Middleware de rate limit padrão do Laravel
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    // Define os arquivos de rotas da aplicação
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Rotas de domínio para o dashboard
            $dashboardDomain = config('app.dashboard_domain');
            if ($dashboardDomain) {
                Route::domain($dashboardDomain)
                    ->middleware('web')
                    ->group(base_path('routes/web.php'));
            }

            // Rotas de domínio para a API
            $apiDomain = config('app.api_domain');
            if ($apiDomain) {
                Route::domain($apiDomain)
                    ->middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));
            }
        },
    )
    // Configura os middlewares globais e de grupos
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplica o middleware de rate limit (ThrottleRequests) em todas as rotas API
        // $middleware->api(prepend:[ThrottleRequests::class]);
        $middleware->alias([
            'idempotency'               => IdempotencyRequiredMiddleware::class,
            'customer.active'           => EnsureCustomerIsActiveMiddleware::class,
            'customer.email.verified'   => EnsureCustomerEmailVerifiedMiddleware::class,
            'customer.document.approved'=> EnsureCustomerDocumentApprovedMiddleware::class,
            'maintenance'               => MaintenanceModeMiddleware::class,
            'auth.merchant'             => \App\Http\Middleware\AuthMerchant::class,
        ]);

        $middleware->api(prepend: [
            MaintenanceModeMiddleware::class,
        ]);

        // Adiciona o CorsMiddleware globalmente
        $middleware->append([
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    // Configura o tratamento de exceções globais
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $exception) {
            return response()->json($exception->toArray(), $exception->status);
        });

        $exceptions->report(function (ApiException $exception) {
            Log::warning('ApiException', [
                'reason' => $exception->reason,
                'status' => $exception->status,
                'message' => $exception->getMessage(),
            ]);
        });
    })
    // Cria e retorna a instância da aplicação
    ->create();
