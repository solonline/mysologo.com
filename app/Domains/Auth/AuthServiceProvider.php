<?php

namespace App\Domains\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Listeners\SendValidationEmail;
use App\Domains\Auth\Repositories\DeviceRepository;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\DeviceAuthService;
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

        $this->loadAuthRoutes();
    }

    private function loadAuthRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/auth')
            ->group(__DIR__.'/Routes/auth.php');

        Route::middleware('web')
            ->group(__DIR__.'/Routes/web.php');
    }
}
