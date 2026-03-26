<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
