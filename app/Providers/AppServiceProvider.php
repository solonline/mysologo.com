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
        // Configura o header Bearer global para a documentação Scramble
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        Scramble::routes(function (Route $route) {
            $uri = $route->uri();
            // Só documenta rotas que começam com 'api/'
            if (!Str::startsWith($uri, 'api/')) {
                return false;
            }
            // Exclui rotas admin e webhook
            if (Str::startsWith($uri, 'api/admin') || $uri === 'api/webhooks/didit') {
                return false;
            }
            return true;
        });
    }
}
