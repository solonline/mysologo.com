<?php

namespace App\Http\Resources\Dashboard;

use App\Domains\Wallet\Enums\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type?->label() ?? ($this->payment_method_type ?? 'Stripe'),
            'direction' => $this->direction,
            'amount' => $this->amount,
            'net_amount' => $this->resolvedNetAmount(),
            'fees_pending' => $this->feesPending(),
            'currency' => $this->currency,
            'status' => $this->customerFacingStatus(),
            'payment_method_type' => $this->payment_method_type,
            'description' => $this->description,
            'counterparty' => $this->when(
                $this->type === TransactionType::P2P,
                function () {
                    $customer = $this->p2pCounterparty();
                    if (! $customer) {
                        return null;
                    }

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'username' => $customer->username,
                        'role' => $this->p2pCounterpartyRole(),
                    ];
                }
            ),
            'available_on' => $this->available_on?->timezone(config('app.timezone'))->toDateString(),
            'funds_available' => $this->customerFundsAvailable(),
            'created_at' => $this->created_at,
        ];
    }
}
