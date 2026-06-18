<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id . '|' . $request->ip())
                : Limit::perMinute(60)->by($request->ip());
        });

        // Limite para criação de payment intents: 10 por minuto por utilizador.
        // Protege contra abuso da API Stripe e escrita excessiva no DB.
        // Retorna 429 com header Retry-After para o cliente saber quando tentar de novo.
        RateLimiter::for('stripe-intent', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Muitas tentativas. Tente novamente em breve.',
                ], 429));
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
            if (Str::startsWith($uri, 'api/admin') || Str::startsWith($uri, 'api/webhooks/')) {
                return false;
            }
            return true;
        });
    }
}
