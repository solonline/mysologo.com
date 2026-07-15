<?php

declare(strict_types=1);

namespace App\Http\Resources\Stripe;

use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StripePaymentIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method_type' => $this->payment_method_type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'description' => $this->description,
            'stripe_transaction_id' => $this->stripe_transaction_id,
            'transaction' => $this->whenLoaded(
                'stripeTransaction',
                fn () => TransactionResource::make($this->stripeTransaction),
            ),
            'expires_at' => $this->expires_at,
            'canceled_at' => $this->canceled_at,
            'succeeded_at' => $this->succeeded_at,
            'created_at' => $this->created_at,
        ];
    }
}
