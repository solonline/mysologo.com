<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\Customer;
use App\Models\Tpa;

class UnlinkCustomerTpaAction
{
    public function execute(Customer $customer, Tpa $tpa): void
    {
        abort_unless($tpa->customer_id === $customer->id, 403);

        $customer->tokens()->where('name', 'tpa_login_' . $tpa->serial)->delete();
        $tpa->delete();
    }
}
