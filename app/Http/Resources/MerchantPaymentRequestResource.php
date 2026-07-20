<?php

namespace App\Http\Resources;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'external_reference' => $this->external_reference,
            'payment_method_types' => $this->payment_method_types,
            'status' => $this->status,
            'settlement_status' => $this->settlementStatus(),
            'url' => url("/pay/{$this->uuid}"),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function settlementStatus(): ?string
    {
        if ($this->status !== 'completed') {
            return null;
        }

        $funding = $this->stripeTransaction?->partner_funding_status;

        if ($funding instanceof PartnerFundingStatus) {
            return match ($funding) {
                PartnerFundingStatus::Completed => 'available',
                PartnerFundingStatus::AwaitingStripePayout,
                PartnerFundingStatus::Pending,
                PartnerFundingStatus::AwaitingLiquidity => 'pending',
                default => 'pending',
            };
        }

        return 'pending';
    }
}
