<?php

namespace App\Http\Resources\Stripe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_method'          => $this->payment_method,
            'label'                   => $this->label,
            'platform_fee_percentage' => $this->platform_fee_percentage,
            'platform_fee_fixed'      => $this->platform_fee_fixed,
            'stripe_fee_percentage'   => $this->stripe_fee_percentage,
            'stripe_fee_fixed'        => $this->stripe_fee_fixed,
        ];
    }
}
