<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardCustomerController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function summary(Request $request)
    {
        $summary = $this->dashboardService->getSummary($request->user());

        return DashboardResource::make($summary);
    }
}
