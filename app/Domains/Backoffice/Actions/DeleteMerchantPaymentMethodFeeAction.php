<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Merchant;

class DeleteMerchantPaymentMethodFeeAction
{
    public function execute(Customer $customer, Merchant $merchant, CustomerPaymentMethodFee $fee): void
    {
        abort_unless($merchant->customer_id === $customer->id, 403);
        abort_unless($fee->merchant_id === $merchant->id, 403);

        $fee->delete();
    }
}
