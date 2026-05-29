<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Account\AccountDetailsResource;
use App\Http\Resources\Account\BalanceResource;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(protected AccountService $accountService)
    {
    }

    /**
     * Get Account Details
     *
     * Returns the Stripe Connect account details linked to the authenticated customer.
     *
     * @tags Account
     */
    public function index(Request $request): AccountDetailsResource
    {
        $details = $this->accountService->getDetails($request->user());

        return AccountDetailsResource::make($details);
    }

    /**
     * Get Balance
     *
     * Returns the customer's available and pending balance. Amounts are in the smallest currency unit (e.g. cents for EUR).
     *
     * @tags Account
     * @queryParam currency string optional Filter by ISO 4217 currency code. Example: EUR
     */
    public function balance(Request $request): AnonymousResourceCollection
    {
        $balances = $this->accountService->getBalances($request->user(), $request->query('currency'));

        return BalanceResource::collection($balances);
    }
}
