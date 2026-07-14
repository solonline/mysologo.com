<?php

namespace App\Domains\Wallet\Controllers;

use App\Domains\Wallet\Services\WalletAccountService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Account\AccountDetailsResource;
use App\Http\Resources\Account\BalanceResource;
use Illuminate\Http\JsonResponse;
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
     *
     * @queryParam currency string optional Filter by ISO 4217 currency code. Example: EUR
     */
    public function balance(Request $request): AnonymousResourceCollection
    {
        return BalanceResource::collection(
            $this->accounts->getBalances($request->user(), $request->query('currency')),
        );
    }

    /**
     * Sync Wallester Balance
     *
     * Fetches the latest Wallester account balance from the partner API and persists it locally.
     *
     * @tags Account
     *
     * @queryParam currency string optional ISO currency code. Example: EUR
     */
    public function syncWallester(Request $request): JsonResponse
    {
        $wallester = $this->accounts->syncWallesterBalance(
            $request->user(),
            $request->query('currency'),
        );

        if ($wallester === null) {
            throw new ApiException(
                ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
                422,
                'Conta Wallester não disponível para sincronização.',
            );
        }

        return response()->json(['wallester' => $wallester]);
    }
}
