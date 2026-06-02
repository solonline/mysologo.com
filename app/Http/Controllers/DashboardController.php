<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\SettingsService;

class DashboardController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}
    public function index()
    {
        $stats = [
            'total_customers'    => Customer::count(),
            'active_customers'   => Customer::where('status', CustomerStatus::ACTIVE)->count(),
            'pending_customers'  => Customer::where('status', CustomerStatus::PENDING)->count(),
            'analysis_customers' => Customer::where('status', CustomerStatus::ANALYSIS)->count(),
            'blocked_customers'  => Customer::where('status', CustomerStatus::BLOCKED)->count(),
            'inactive_customers' => Customer::where('status', CustomerStatus::INACTIVE)->count(),
            'pending_documents'  => Customer::where('doc_status', 'pending')->count(),
            'total_balance'      => 0,
            'maintenance_mode'    => $this->settingsService->getByKey('system.maintenance_mode') === 'true',
        ];

        $recentCustomers   = Customer::where('type', '!=', 'platform')->latest()->limit(10)->get();
        $platformCustomers = Customer::where('type', 'platform')->latest()->limit(5)->get();

        $stats['platform_customers'] = Customer::where('type', 'platform')->count();

        return view('dashboard.index', compact('stats', 'recentCustomers', 'platformCustomers'));
    }
}
