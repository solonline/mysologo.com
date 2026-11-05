<?php

namespace App\Domains\Backoffice;

use App\Domains\Backoffice\Actions\CancelAccountPayableAction;
use App\Domains\Backoffice\Actions\CreateAccountPayableAction;
use App\Domains\Backoffice\Actions\CreateCustomerFromBackofficeAction;
use App\Domains\Backoffice\Actions\CreateCustomerWallesterCardAction;
use App\Domains\Backoffice\Actions\LinkCustomerWallesterCardAction;
use App\Domains\Backoffice\Actions\CreateNewsAction;
use App\Domains\Backoffice\Actions\CreateRoleAction;
use App\Domains\Backoffice\Actions\CreateStaffUserAction;
use App\Domains\Backoffice\Actions\CreateStripeFeeAction;
use App\Domains\Backoffice\Actions\DeleteAccountPayableAction;
use App\Domains\Backoffice\Actions\DeleteAccountPayableAttachmentAction;
use App\Domains\Backoffice\Actions\DeleteNewsAction;
use App\Domains\Backoffice\Actions\DeleteRoleAction;
use App\Domains\Backoffice\Actions\DeleteStaffUserAction;
use App\Domains\Backoffice\Actions\DeleteStripeFeeAction;
use App\Domains\Backoffice\Actions\MarkAccountPayablePaidAction;
use App\Domains\Backoffice\Actions\RetryAwaitingWallesterTopupsAction;
use App\Domains\Backoffice\Actions\RetryCustomerProvisioningAction;
use App\Domains\Backoffice\Actions\RetryStripeFundingAction;
use App\Domains\Backoffice\Actions\StoreAccountPayableAttachmentsAction;
use App\Domains\Backoffice\Actions\UpdateAccountPayableAction;
use App\Domains\Backoffice\Actions\UpdateCustomerFromBackofficeAction;
use App\Domains\Backoffice\Actions\UpdateNewsAction;
use App\Domains\Backoffice\Actions\UpdateRoleAction;
use App\Domains\Backoffice\Actions\UpdateStaffUserAction;
use App\Domains\Backoffice\Actions\UpdateStripeFeeAction;
use App\Domains\Backoffice\Queries\GetAcceptedCurrenciesQuery;
use App\Domains\Backoffice\Queries\GetAuditLogDetailQuery;
use App\Domains\Backoffice\Queries\GetCustomerDetailQuery;
use App\Domains\Backoffice\Queries\GetDashboardOverviewQuery;
use App\Domains\Backoffice\Queries\GetPlatformFeeSubAccountOverviewQuery;
use App\Domains\Backoffice\Queries\GetPlatformRevenueOverviewQuery;
use App\Domains\Backoffice\Queries\GetWallesterFundingOverviewQuery;
use App\Domains\Backoffice\Queries\ListAccountsPayableQuery;
use App\Domains\Backoffice\Queries\ListAuditLogsQuery;
use App\Domains\Backoffice\Queries\ListCustomersQuery;
use App\Domains\Backoffice\Queries\SearchCustomersByUsernameQuery;
use App\Domains\Backoffice\Queries\ListFundingPartnerMovementsQuery;
use App\Domains\Backoffice\Queries\ListNewsQuery;
use App\Domains\Backoffice\Queries\ListRolesQuery;
use App\Domains\Backoffice\Queries\ListStripeFeesQuery;
use App\Domains\Backoffice\Queries\ListStripeTransactionsQuery;
use App\Domains\Backoffice\Queries\ListUsersQuery;
use App\Domains\Backoffice\Services\NewsImageService;
use App\Domains\Backoffice\Services\SettingsManagementService;
use App\Domains\Backoffice\Services\StaffNotificationService;
use App\Domains\Backoffice\Services\StaffSessionService;
use App\Domains\Backoffice\Services\StripeFeeCacheService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BackofficeServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private array $containerSingletons = [
        GetDashboardOverviewQuery::class,
        GetPlatformFeeSubAccountOverviewQuery::class,
        GetPlatformRevenueOverviewQuery::class,
        GetWallesterFundingOverviewQuery::class,
        GetAcceptedCurrenciesQuery::class,
        ListCustomersQuery::class,
        SearchCustomersByUsernameQuery::class,
        GetCustomerDetailQuery::class,
        ListUsersQuery::class,
        ListRolesQuery::class,
        ListNewsQuery::class,
        ListStripeFeesQuery::class,
        ListAccountsPayableQuery::class,
        ListAuditLogsQuery::class,
        ListStripeTransactionsQuery::class,
        ListFundingPartnerMovementsQuery::class,
        GetAuditLogDetailQuery::class,
        CreateCustomerFromBackofficeAction::class,
        UpdateCustomerFromBackofficeAction::class,
        RetryCustomerProvisioningAction::class,
        RetryStripeFundingAction::class,
        CreateCustomerWallesterCardAction::class,
        LinkCustomerWallesterCardAction::class,
        RetryAwaitingWallesterTopupsAction::class,
        CreateStaffUserAction::class,
        UpdateStaffUserAction::class,
        DeleteStaffUserAction::class,
        CreateRoleAction::class,
        UpdateRoleAction::class,
        DeleteRoleAction::class,
        CreateNewsAction::class,
        UpdateNewsAction::class,
        DeleteNewsAction::class,
        CreateStripeFeeAction::class,
        UpdateStripeFeeAction::class,
        DeleteStripeFeeAction::class,
        CreateAccountPayableAction::class,
        UpdateAccountPayableAction::class,
        DeleteAccountPayableAction::class,
        MarkAccountPayablePaidAction::class,
        CancelAccountPayableAction::class,
        StoreAccountPayableAttachmentsAction::class,
        DeleteAccountPayableAttachmentAction::class,
        StaffNotificationService::class,
        StaffSessionService::class,
        SettingsManagementService::class,
        StripeFeeCacheService::class,
        NewsImageService::class,
    ];

    public function register(): void
    {
        foreach ($this->containerSingletons as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/backoffice')
            ->group(__DIR__.'/Routes/backoffice.php');
    }
}
