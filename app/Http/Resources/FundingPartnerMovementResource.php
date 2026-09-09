<?php

namespace App\Http\Resources;

use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
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
        $isP2p = $this->isP2pTransfer();
        $direction = $this->direction;
        $counterparty = $isP2p ? $this->counterpartyWallesterAccount?->customer : null;

        return [
            'id' => $this->id,
            'provider' => $this->provider?->value,
            'external_account_id' => $this->external_account_id,
            'direction' => $direction?->value,
            'type' => $this->type?->value,
            'type_label' => $this->displayTypeLabel(),
            'source_label' => $this->displaySourceLabel(),
            'is_p2p' => $isP2p,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'original_amount' => $this->original_amount,
            'original_currency' => $this->original_currency,
            'fees_amount' => $this->fees_amount,
            'platform_fees_amount' => $this->platform_fees_amount,
            'transfer_group_id' => $this->transfer_group_id,
            'counterparty_external_account_id' => $this->counterparty_external_account_id,
            'counterparty' => $this->when(
                $isP2p,
                fn () => $counterparty ? [
                    'id' => $counterparty->id,
                    'name' => $counterparty->name,
                    'username' => $counterparty->username,
                    'avatar_url' => $counterparty->publicAvatarUrl(),
                    'role' => $direction === FundingPartnerMovementDirection::In ? 'sender' : 'recipient',
                ] : null,
            ),
            'partner_external_id' => $this->partner_external_id,
            'status' => $this->status?->value,
            'source' => $this->source?->value,
            'description' => $this->displayDescription(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
