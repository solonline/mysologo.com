<?php

namespace App\Http\Resources\Stripe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'payment_method'           => $this->payment_method,
            'label'                    => $this->label,
            'currency'                 => $this->currency,
            'stripe_fee_percentage'    => $this->stripe_fee_percentage,
            'stripe_fee_fixed'         => $this->stripe_fee_fixed,
            'platform_fee_percentage'  => $this->platform_fee_percentage,
            'platform_fee_fixed'       => $this->platform_fee_fixed,
        ];
    }
}
