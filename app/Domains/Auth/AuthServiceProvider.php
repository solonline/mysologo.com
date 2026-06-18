<?php

namespace App\Domains\Auth;

use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Domains\Auth\Repositories\DeviceRepository;
use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Listeners\SendValidationEmail;
use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Auth\Services\AuthService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeviceRepositoryInterface::class, DeviceRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->singleton(DeviceAuthService::class);
    }

    public function boot(): void
    {
        // Registro explícito do listener de e-mail de validação ao registrar utilizador.
        Event::listen(UserRegistered::class, SendValidationEmail::class);

        // Rotas v2 do domínio Auth — paralelas às legadas em routes/api.php (fase de testes).
        $this->loadAuthRoutes();
    }

    private function loadAuthRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v2/auth')
            ->group(__DIR__ . '/Routes/auth.php');
    }
}
