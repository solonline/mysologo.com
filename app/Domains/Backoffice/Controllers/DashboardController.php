<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetDashboardOverviewQuery;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __construct(
        protected GetDashboardOverviewQuery $overviewQuery,
    ) {}

    public function index()
    {
        $overview = $this->overviewQuery->execute();

        return view('dashboard.index', [
            'stats' => $overview->stats,
            'recentCustomers' => $overview->recentCustomers,
            'platformCustomers' => $overview->platformCustomers,
        ]);
    }
}
