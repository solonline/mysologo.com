<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetPlatformRevenueOverviewQuery;
use App\Domains\Backoffice\Queries\ListStripeTransactionsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlatformRevenueController extends Controller
{
    public function __construct(
        protected GetPlatformRevenueOverviewQuery $platformRevenueOverviewQuery,
        protected ListStripeTransactionsQuery $listStripeTransactions,
    ) {}

    public function index(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $perPage = min((int) $request->input('per_page', 25), 100);

        $overview = $this->platformRevenueOverviewQuery->execute($from, $to);

        $transactions = $this->listStripeTransactions->execute(
            array_filter([
                'with_platform_fee' => '1',
                'after_accounting_cutoff' => '1',
                'from' => $from,
                'to' => $to,
            ]),
            $perPage,
        )->withQueryString();

        return view('dashboard.platform-revenue.index', [
            'overview' => $overview,
            'transactions' => $transactions,
        ]);
    }
}
