<?php

namespace App\Http\Resources;

use App\Models\FundingPartnerMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FundingPartnerMovement */
class FundingPartnerMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider?->value,
            'external_account_id' => $this->external_account_id,
            'direction' => $this->direction?->value,
            'type' => $this->type?->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transfer_group_id' => $this->transfer_group_id,
            'counterparty_external_account_id' => $this->counterparty_external_account_id,
            'partner_external_id' => $this->partner_external_id,
            'status' => $this->status?->value,
            'source' => $this->source?->value,
            'description' => $this->description,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
