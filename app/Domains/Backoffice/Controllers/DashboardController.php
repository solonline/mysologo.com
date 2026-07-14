<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetDashboardOverviewQuery;
use App\Domains\Backoffice\Queries\GetPlatformRevenueOverviewQuery;
use App\Domains\Backoffice\Queries\GetWallesterFundingOverviewQuery;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __construct(
        protected GetDashboardOverviewQuery $overviewQuery,
        protected GetWallesterFundingOverviewQuery $wallesterFundingOverviewQuery,
        protected GetPlatformRevenueOverviewQuery $platformRevenueOverviewQuery,
    ) {}

    public function index()
    {
        $overview = $this->overviewQuery->execute();
        $wallesterFunding = $this->wallesterFundingOverviewQuery->execute();
        $platformRevenue = $this->platformRevenueOverviewQuery->execute();

        return view('dashboard.index', [
            'stats' => $overview->stats,
            'recentCustomers' => $overview->recentCustomers,
            'platformCustomers' => $overview->platformCustomers,
            'wallesterMainAccounts' => $wallesterFunding['mainAccounts'],
            'wallesterTopupFundingCounts' => $wallesterFunding['topupFundingCounts'],
            'platformRevenue' => $platformRevenue,
        ]);
    }
}
