<?php

namespace App\Domains\Stripe\Jobs;

use App\Domains\Stripe\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStripeWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $payload) {}

    public function handle(StripeService $stripeService): void
    {
        if (StripeService::isTestModeEvent($this->payload)) {
            return;
        }

        $this->enqueuePayoutPaidIfPresent();
        $stripeService->handleWebhook($this->payload);
    }

    /**
     * Payout settlement must not depend only on StripeService's event match.
     * A missed match (old worker, unknown type shape) would otherwise leave
     * merchant payments stuck in awaiting_stripe_payout.
     */
    private function enqueuePayoutPaidIfPresent(): void
    {
        $object = $this->payload['data']['object'] ?? [];
        $type = $this->payload['type'] ?? null;
        $isPayout = $type === 'payout.paid' || ($object['object'] ?? null) === 'payout';

        if (! $isPayout) {
            return;
        }

        $payoutId = $object['id'] ?? null;

        if (! is_string($payoutId) || ! str_starts_with($payoutId, 'po_')) {
            return;
        }

        if (($object['status'] ?? null) !== 'paid') {
            return;
        }

        ProcessStripePayoutPaidJob::dispatch(
            $payoutId,
            strtolower((string) ($object['currency'] ?? 'eur')),
            (bool) ($object['livemode'] ?? $this->payload['livemode'] ?? true),
        );
    }
}
