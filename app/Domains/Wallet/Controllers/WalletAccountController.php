<?php

namespace App\Domains\Wallet\Controllers;

use App\Domains\Wallet\Services\WalletAccountService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Account\AccountDetailsResource;
use App\Http\Resources\Account\BalanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletAccountController extends Controller
{
    public function __construct(
        protected WalletAccountService $accounts,
    ) {}

    /**
     * Get Account Details
     *
     * @tags Account
     */
    public function index(Request $request): AccountDetailsResource
    {
        return AccountDetailsResource::make($this->accounts->getDetails($request->user()));
    }

    /**
     * Get Balance
     *
     * @tags Account
     * @queryParam currency string optional Filter by ISO 4217 currency code. Example: EUR
     */
    public function balance(Request $request): AnonymousResourceCollection
    {
        return BalanceResource::collection(
            $this->accounts->getBalances($request->user(), $request->query('currency')),
        );
    }
}
