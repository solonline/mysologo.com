<?php

namespace App\Domains\Ebury\Gateways;

use App\Domains\Ebury\Contracts\EburyGatewayInterface;
use App\Models\Customer;
use App\Models\CustomerEburyAccount;
use Illuminate\Support\Facades\Log;

class SimulatedEburyGateway implements EburyGatewayInterface
{
    public function submitCustomer(Customer $customer, CustomerEburyAccount $account): array
    {
        $externalId = 'sim:' . $customer->id;

        Log::channel('ebury')->info('Ebury simulation: customer submitted', [
            'customer_id' => $customer->id,
            'ebury_account_id' => $externalId,
        ]);

        return [
            'account_id' => $externalId,
            'status' => 'in_review',
        ];
    }
}
