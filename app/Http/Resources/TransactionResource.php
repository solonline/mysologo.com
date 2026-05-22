<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'direction'           => $this->direction,
            'amount'              => $this->amount,
            'fee'                 => $this->stripe_fee + $this->platform_fee,
            'net_amount'          => $this->net_amount,
            'currency'            => $this->currency,
            'status'              => $this->status,
            'payment_method_type' => $this->payment_method_type,
            'description'         => $this->description,

            'bank_transfer' => $this->when(
                $this->bank_transfer_type !== null,
                fn () => [
                    'type'        => $this->bank_transfer_type,
                    'reference'   => $this->bank_transfer_reference,
                    'sender_name' => $this->bank_transfer_sender_name,
                    'bic'         => $this->bank_transfer_bic,
                    'iban_last4'  => $this->bank_transfer_iban_last4,
                ]
            ),

            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'stripe_charge_id'         => $this->stripe_charge_id,

            'settles_at' => $this->settles_at,
            'created_at' => $this->created_at,
        ];
    }
}
