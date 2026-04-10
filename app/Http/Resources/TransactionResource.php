<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'type'                     => $this->type,
            'direction'                => $this->direction,
            'amount'                   => $this->amount,
            'stripe_fee'               => $this->stripe_fee,
            'platform_fee'             => $this->platform_fee,
            'net_amount'               => $this->net_amount,
            'currency'                 => $this->currency,
            'status'                   => $this->status,
            'payment_method_type'      => $this->payment_method_type,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'description'              => $this->description,
            'created_at'               => $this->created_at,
        ];
    }
}
