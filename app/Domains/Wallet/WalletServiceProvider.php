<?php

namespace App\Domains\Wallet;

use App\Domains\Wallet\Commands\RunPlatformRevenueReportCommand;
use App\Domains\Wallet\Commands\RunWalletAuditCommand;
use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Domains\Wallet\Repositories\TransactionRepository;
use App\Domains\Wallet\Services\Audit\WalletAuditService;
use App\Domains\Wallet\Services\BankTransferTopupNotifier;
use App\Domains\Wallet\Services\FundingLiquidityNotifier;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\FundingPartnerTransferResolver;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\PartnerTopupFundingCompleter;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Domains\Wallet\Services\SettlementService;
use App\Domains\Wallet\Services\StripePaymentWalletService;
use App\Domains\Wallet\Services\StripePayoutSettlementService;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Domains\Wallet\Services\WallesterMainAccountSyncService;
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
        StripePaymentWalletService::class,
        SettlementService::class,
        StripePayoutSettlementService::class,
        TopupWallesterFundingPolicy::class,
        PartnerTopupFundingCompleter::class,
        BankTransferTopupNotifier::class,
        FundingLiquidityNotifier::class,
        WallesterMainAccountSyncService::class,
        FundingPartnerMovementService::class,
        FundingPartnerTransferResolver::class,
        PlatformRevenueService::class,
        WalletAuditService::class,
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
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunWalletAuditCommand::class,
                RunPlatformRevenueReportCommand::class,
            ]);
        }

        Route::middleware('api')
            ->prefix('api/wallet')
            ->group(__DIR__.'/Routes/wallet.php');
    }
}
