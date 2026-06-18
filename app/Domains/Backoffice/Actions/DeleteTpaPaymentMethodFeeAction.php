<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Tpa;

class DeleteTpaPaymentMethodFeeAction
{
    public function execute(Customer $customer, Tpa $tpa, CustomerPaymentMethodFee $fee): void
    {
        abort_unless($tpa->customer_id === $customer->id, 403);
        abort_unless($fee->tpa_id === $tpa->id, 403);

        $fee->delete();
    }
}
