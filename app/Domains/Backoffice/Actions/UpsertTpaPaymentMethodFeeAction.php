<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\CustomerPaymentMethodFeeData;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Tpa;

class UpsertTpaPaymentMethodFeeAction
{
    public function execute(Customer $customer, Tpa $tpa, CustomerPaymentMethodFeeData $data): CustomerPaymentMethodFee
    {
        abort_unless($tpa->customer_id === $customer->id, 403);

        return CustomerPaymentMethodFee::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'tpa_id' => $tpa->id,
                'merchant_id' => null,
                'payment_method' => $data->paymentMethod,
                'currency' => $data->currency,
            ],
            [
                'label' => $data->label,
                'fee_percentage' => $data->feePercentage,
                'fee_fixed' => $data->feeFixed,
                'is_active' => $data->isActive,
            ],
        );
    }
}
