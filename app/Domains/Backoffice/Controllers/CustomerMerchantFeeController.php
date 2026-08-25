<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\DeleteMerchantPaymentMethodFeeAction;
use App\Domains\Backoffice\Actions\UpsertMerchantPaymentMethodFeeAction;
use App\Domains\Backoffice\DTOs\CustomerPaymentMethodFeeData;
use App\Domains\Backoffice\Requests\StoreCustomerPaymentMethodFeeRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Merchant;

class CustomerMerchantFeeController extends Controller
{
    public function __construct(
        protected UpsertMerchantPaymentMethodFeeAction $upsertFee,
        protected DeleteMerchantPaymentMethodFeeAction $deleteFee,
    ) {}

    public function storeFee(StoreCustomerPaymentMethodFeeRequest $request, Customer $customer, Merchant $merchant)
    {
        // dd($request->validated());
        $this->upsertFee->execute(
            $customer,
            $merchant,
            CustomerPaymentMethodFeeData::fromValidated(
                $request->validated(),
                $request->boolean('is_active', true),
            ),
        );

        return back()->with('success', 'Taxa guardada.');
    }

    public function destroyFee(Customer $customer, Merchant $merchant, CustomerPaymentMethodFee $fee)
    {
        $this->deleteFee->execute($customer, $merchant, $fee);

        return back()->with('success', 'Taxa removida.');
    }
}
