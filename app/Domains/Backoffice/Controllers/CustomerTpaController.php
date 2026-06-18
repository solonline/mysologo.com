<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\DeleteTpaPaymentMethodFeeAction;
use App\Domains\Backoffice\Actions\UnlinkCustomerTpaAction;
use App\Domains\Backoffice\Actions\UpsertTpaPaymentMethodFeeAction;
use App\Domains\Backoffice\DTOs\TpaPaymentMethodFeeData;
use App\Domains\Backoffice\Requests\StoreTpaPaymentMethodFeeRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Tpa;

class CustomerTpaController extends Controller
{
    public function __construct(
        protected UnlinkCustomerTpaAction $unlinkTpa,
        protected UpsertTpaPaymentMethodFeeAction $upsertFee,
        protected DeleteTpaPaymentMethodFeeAction $deleteFee,
    ) {}

    public function destroy(Customer $customer, Tpa $tpa)
    {
        $this->unlinkTpa->execute($customer, $tpa);

        return back()->with('success', 'TPA removido.');
    }

    public function storeFee(StoreTpaPaymentMethodFeeRequest $request, Customer $customer, Tpa $tpa)
    {
        $this->upsertFee->execute(
            $customer,
            $tpa,
            TpaPaymentMethodFeeData::fromValidated(
                $request->validated(),
                $request->boolean('is_active', true),
            ),
        );

        return back()->with('success', 'Taxa guardada.');
    }

    public function destroyFee(Customer $customer, Tpa $tpa, CustomerPaymentMethodFee $fee)
    {
        $this->deleteFee->execute($customer, $tpa, $fee);

        return back()->with('success', 'Taxa removida.');
    }
}
