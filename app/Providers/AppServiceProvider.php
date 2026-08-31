<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureLogDirectories();

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id.'|'.$request->ip())
                : Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('stripe-intent', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Muitas tentativas. Tente novamente em breve.',
                ], 429));
        });
    }

    private function ensureLogDirectories(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        foreach (config('logging.directories', []) as $directory) {
            $path = storage_path('logs/'.$directory);

            if (! is_dir($path)) {
                mkdir($path, 02775, true);
            }
        }
    }
}
