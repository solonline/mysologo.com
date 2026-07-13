<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stripe = $this->resource['stripe'];
        $wallester = $this->resource['wallester'];

        return [
            'stripe' => $stripe ? [
                'stripe_customer_id' => $stripe['stripe_customer_id'],
                'stripe_payment_method_id' => $stripe['stripe_payment_method_id'],
                'currency' => $stripe['currency'],
                'status' => $stripe['status'],
                'funding_status' => $stripe['funding_status'],
                'funding_status_reason' => $stripe['funding_status_reason'],
                'viban' => $stripe['viban'],
                'funding_type' => $stripe['funding_type'],
                'bank_transfer_type' => $stripe['bank_transfer_type'],
                'funding_iban' => $stripe['funding_iban'],
                'funding_bic' => $stripe['funding_bic'],
                'funding_account_holder_name' => $stripe['funding_account_holder_name'],
                'funding_country' => $stripe['funding_country'],
                'funding_financial_addresses' => $stripe['funding_financial_addresses'],
            ] : null,
            'wallester' => $wallester ? [
                'wallester_account_id' => $wallester['wallester_account_id'],
                'iban' => $wallester['iban'],
                'viban' => $wallester['viban'],
                'swift_code' => $wallester['swift_code'],
                'receiver_name' => $wallester['receiver_name'],
                'bank_name' => $wallester['bank_name'],
                'currency_code' => $wallester['currency_code'],
                'status' => $wallester['status'],
                'balance' => $wallester['balance'],
                'available_amount' => $wallester['available_amount'],
            ] : null,
        ];
    }
}
