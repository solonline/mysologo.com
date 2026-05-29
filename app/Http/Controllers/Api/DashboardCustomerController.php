<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardCustomerController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    /**
     * Dashboard Summary
     *
     * Returns the customer's current balances and recent transactions.
     *
     * @tags Dashboard
     */
    public function summary(Request $request)
    {
        $summary = $this->dashboardService->getSummary($request->user());

        return DashboardResource::make($summary);
    }
}
