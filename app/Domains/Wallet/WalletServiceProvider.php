<?php

namespace App\Domains\Wallet;

use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Domains\Wallet\Repositories\TransactionRepository;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\SettlementService;
use App\Domains\Wallet\Services\WalletAccountService;
use App\Domains\Wallet\Services\WalletDashboardService;
use App\Domains\Wallet\Services\WalletTransactionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private array $containerSingletons = [
        WalletAccountService::class,
        WalletDashboardService::class,
        WalletTransactionService::class,
        LedgerService::class,
        SettlementService::class,
    ];

    public function register(): void
    {
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);

        foreach ($this->containerSingletons as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/wallet')
            ->group(__DIR__.'/Routes/wallet.php');
    }
}
