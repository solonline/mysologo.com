<?php

namespace App\Providers;

use App\Models\Merchant;
use Closure;
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
                ->response($this->tooManyAttemptsResponse());
        });

        RateLimiter::for('merchant-payment-requests', function (Request $request) {
            return Limit::perMinute(120)
                ->by($this->merchantLimitKey($request, 'payment-requests'))
                ->response($this->tooManyAttemptsResponse());
        });
    }

    private function merchantLimitKey(Request $request, string $prefix): string
    {
        $merchant = $request->attributes->get('merchant');

        if ($merchant instanceof Merchant) {
            return $prefix.':merchant:'.$merchant->id;
        }

        $routeMerchant = $request->route('merchant');

        if ($routeMerchant instanceof Merchant) {
            return $prefix.':merchant:'.$routeMerchant->id;
        }

        if (is_numeric($routeMerchant)) {
            return $prefix.':merchant:'.$routeMerchant;
        }

        return $prefix.':'.($request->user()?->id ?? $request->ip());
    }

    private function tooManyAttemptsResponse(): Closure
    {
        return fn () => response()->json([
            'message' => 'Muitas tentativas. Tente novamente em breve.',
        ], 429);
    }

    private function ensureLogDirectories(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
        $webUser = 'www-data';
        $canChown = $isRoot && function_exists('posix_getpwnam') && posix_getpwnam($webUser);

        foreach (config('logging.directories', []) as $directory) {
            $path = storage_path('logs/'.$directory);

            if (! is_dir($path)) {
                $previousUmask = umask(0);

                try {
                    mkdir($path, 02775, true);
                } finally {
                    umask($previousUmask);
                }
            }

            if ($isRoot && is_dir($path)) {
                chmod($path, 02775);

                if ($canChown) {
                    chown($path, $webUser);
                    chgrp($path, $webUser);
                }
            }
        }
    }
}
