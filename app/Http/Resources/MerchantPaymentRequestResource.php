<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'amount'             => $this->amount,
            'currency'           => $this->currency,
            'description'        => $this->description,
            'external_reference' => $this->external_reference,
            'status'             => $this->status,
            'url'                => url("/pay/{$this->uuid}"),
            'expires_at'         => $this->expires_at?->toIso8601String(),
        ];
    }
}
