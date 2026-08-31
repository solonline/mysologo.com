<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\DashboardOverview;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Models\Customer;
use App\Models\CustomerEburyAccount;
use App\Models\FundingPartnerMovement;

class GetDashboardOverviewQuery
{
    public function __construct(
        protected SettingsService $settingsService,
    ) {}

    public function execute(): DashboardOverview
    {
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', CustomerLifecycleStatus::ACTIVE)->count(),
            'pending_customers' => Customer::whereIn('status', array_map(
                fn (CustomerLifecycleStatus $s) => $s->value,
                CustomerLifecycleStatus::pendingStatuses()
            ))->count(),
            'analysis_customers' => Customer::whereIn('status', [
                CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW->value,
                CustomerLifecycleStatus::PROVISIONING->value,
                CustomerLifecycleStatus::EBURY_UNDER_REVIEW->value,
            ])->count(),
            'provisioning_failures' => Customer::query()
                ->where('status', CustomerLifecycleStatus::PROVISIONING)
                ->whereHas('wallesterAccounts', fn ($query) => $query->where('status', IntegrationAccountStatus::FAILED))
                ->count(),
            'blocked_customers' => Customer::whereIn('status', [
                CustomerLifecycleStatus::BLOCKED->value,
                CustomerLifecycleStatus::IDENTITY_REJECTED->value,
                CustomerLifecycleStatus::EBURY_REJECTED->value,
            ])->count(),
            'inactive_customers' => Customer::where('status', CustomerLifecycleStatus::SUSPENDED)->count(),
            'pending_documents' => Customer::where('doc_status', 'pending')->count(),
            'total_movements' => FundingPartnerMovement::count(),
            'movements_completed' => FundingPartnerMovement::where('status', FundingPartnerMovementStatus::Completed)->count(),
            'movements_pending' => FundingPartnerMovement::where('status', FundingPartnerMovementStatus::Pending)->count(),
            'movements_failed' => FundingPartnerMovement::where('status', FundingPartnerMovementStatus::Failed)->count(),
            'ebury_active_accounts' => CustomerEburyAccount::where('status', IntegrationAccountStatus::ACTIVE)->count(),
            'total_balance' => 0,
            'maintenance_mode' => $this->settingsService->getByKey('system.maintenance_mode') === 'true',
            'platform_customers' => Customer::where('type', 'platform')->count(),
            'merchant_customers' => Customer::whereHas('merchants')->count(),
        ];

        $recentCustomers = Customer::where('type', '!=', 'platform')->latest()->limit(10)->get();
        $platformCustomers = Customer::where('type', 'platform')->latest()->limit(5)->get();

        return new DashboardOverview($stats, $recentCustomers, $platformCustomers);
    }
}
