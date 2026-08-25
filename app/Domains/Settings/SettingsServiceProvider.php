<?php

namespace App\Domains\Settings;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Domains\Settings\Repositories\SettingRepository;
use App\Domains\Settings\Services\DiditSettingsService;
use App\Domains\Settings\Services\MailSettingsService;
use App\Domains\Settings\Services\SettingsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SettingsRepositoryInterface::class, SettingRepository::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(DiditSettingsService::class);
        $this->app->singleton(MailSettingsService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/settings')
            ->group(__DIR__.'/Routes/settings.php');

        $this->app->booted(function () {
            $this->applyMailSettings();
        });
    }

    private function applyMailSettings(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $this->app->make(MailSettingsService::class)->apply();
        } catch (\Throwable) {
            // Base de dados ainda não disponível (migrate, CI, etc.)
        }
    }
}
