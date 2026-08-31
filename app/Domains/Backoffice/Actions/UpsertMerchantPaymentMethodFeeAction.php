<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\CustomerPaymentMethodFeeData;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Merchant;

class UpsertMerchantPaymentMethodFeeAction
{
    public function execute(Customer $customer, Merchant $merchant, CustomerPaymentMethodFeeData $data): CustomerPaymentMethodFee
    {
        abort_unless($merchant->customer_id === $customer->id, 403);

        return CustomerPaymentMethodFee::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'merchant_id' => $merchant->id,
                'tpa_id' => null,
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
