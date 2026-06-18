<?php

namespace App\Domains\Stripe\Listeners;

use App\Domains\Onboarding\Events\CustomerApproved;
use App\Domains\Stripe\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateStripeCustomer implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'stripe';

    public function __construct(protected StripeService $stripeService)
    {
    }

    public function handle(CustomerApproved $event): void
    {
        $customer = $event->customer->fresh(['stripeAccount']);

        if ($customer->stripeAccount) {
            return;
        }

        try {
            $this->stripeService->createCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning('Falha ao criar customer no Stripe via listener', [
                'customer_id' => $customer->id,
                'exception' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
