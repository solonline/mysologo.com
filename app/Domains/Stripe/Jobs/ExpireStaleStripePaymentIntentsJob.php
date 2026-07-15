<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Jobs;

use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Stripe\Services\StripePaymentIntentService;
use App\Models\StripePaymentIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireStaleStripePaymentIntentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        StripeGatewayInterface $stripeGateway,
        StripePaymentIntentService $paymentIntents,
    ): void {
        $openStatuses = [
            StripePaymentIntentStatus::RequiresPaymentMethod,
            StripePaymentIntentStatus::RequiresConfirmation,
            StripePaymentIntentStatus::RequiresAction,
        ];

        StripePaymentIntent::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, $openStatuses))
            ->where('created_at', '<', now()->subHours(24))
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($stripeGateway, $paymentIntents): void {
                foreach ($records as $paymentIntent) {
                    $this->syncOne($paymentIntent, $stripeGateway, $paymentIntents);
                }
            });
    }

    private function syncOne(
        StripePaymentIntent $paymentIntent,
        StripeGatewayInterface $stripeGateway,
        StripePaymentIntentService $paymentIntents,
    ): void {
        $currency = strtolower((string) $paymentIntent->currency);
        $result = $stripeGateway->getPaymentIntent($paymentIntent->stripe_payment_intent_id, $currency);

        if (($result['status'] ?? null) !== 'success') {
            Log::channel('stripe')->warning('expire_stale_payment_intents: sync failed', [
                'payment_intent_id' => $paymentIntent->id,
                'stripe_payment_intent_id' => $paymentIntent->stripe_payment_intent_id,
                'message' => $result['message'] ?? null,
            ]);

            if ($paymentIntent->expires_at !== null && $paymentIntent->expires_at->isPast()) {
                $paymentIntents->markCanceled($paymentIntent);
            }

            return;
        }

        $intent = $result['data'];
        $stripeStatus = StripePaymentIntentStatus::fromStripe($intent['status'] ?? null);

        if ($stripeStatus->isTerminal()) {
            $paymentIntents->syncStatusFromStripePayload($paymentIntent, $intent);

            return;
        }

        if ($paymentIntent->expires_at !== null && $paymentIntent->expires_at->isPast()) {
            $paymentIntents->markCanceled($paymentIntent, $intent);
        }
    }
}
