<?php


// Middleware customizado para idempotência de requisições
use App\Http\Middleware\IdempotencyRequiredMiddleware;
// Classe principal da aplicação Laravel
use Illuminate\Foundation\Application;
// Configuração de tratamento de exceções
use Illuminate\Foundation\Configuration\Exceptions;
// Configuração de middlewares globais e de grupos
use Illuminate\Foundation\Configuration\Middleware;
// Middleware de rate limit padrão do Laravel
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    // Define os arquivos de rotas da aplicação
    ->withRouting(
        web: __DIR__ . '/../routes/web.php', // Rotas web
        api: __DIR__ . '/../routes/api.php', // Rotas API
        commands: __DIR__ . '/../routes/console.php', // Comandos Artisan customizados
        health: '/up', // Endpoint de health check
    )
    // Configura os middlewares globais e de grupos
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplica o middleware de rate limit (ThrottleRequests) em todas as rotas API
        // $middleware->api(prepend:[ThrottleRequests::class]);
        // Cria um alias para o middleware de idempotência
        $middleware->alias(['idempotency', IdempotencyRequiredMiddleware::class]);

    })
    // Configura o tratamento de exceções globais
    ->withExceptions(function (Exceptions $exceptions): void {
        // Adicione handlers customizados aqui, se necessário
    })
    // Cria e retorna a instância da aplicação
    ->create();
