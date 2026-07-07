<?php

namespace App\Domains\Wallet\Controllers;

use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\FundingPartnerMovementResource;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WalletFundingAccountController extends Controller
{
    public function __construct(
        protected FundingPartnerMovementService $movements,
    ) {}

    /**
     * List Funding Partner Movements
     *
     * @tags Wallet
     *
     * @queryParam per_page integer optional Results per page (max 100). Defaults to 15.
     */
    public function movements(Request $request, int $id): AnonymousResourceCollection
    {
        $account = $this->resolveOwnedFundingAccount($request->user(), $id);

        $perPage = min((int) $request->input('per_page', 15), 100);

        return FundingPartnerMovementResource::collection(
            $this->movements->listForFundingAccount($account, $perPage),
        );
    }

    private function resolveOwnedFundingAccount(Customer $customer, int $id): CustomerWallesterAccount
    {
        $account = CustomerWallesterAccount::query()
            ->whereKey($id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $account instanceof CustomerWallesterAccount) {
            throw new NotFoundHttpException('Funding account not found.');
        }

        if (! $account->status->isOperational()) {
            throw new ApiException(
                ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
                422,
                'Conta de funding não está activa.',
            );
        }

        return $account;
    }
}
