<?php

namespace App\Domains\Wallet\Controllers;

use App\Domains\Wallet\Services\WalletDashboardService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardResource;
use Illuminate\Http\Request;

class WalletDashboardController extends Controller
{
    public function __construct(
        protected WalletDashboardService $dashboard,
    ) {}

    /**
     * Dashboard Summary
     *
     * Returns the customer's current balances and recent transactions.
     *
     * @tags Dashboard
     */
    public function summary(Request $request)
    {
        return DashboardResource::make($this->dashboard->getSummary($request->user()));
    }
}
