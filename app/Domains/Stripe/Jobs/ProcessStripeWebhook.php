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
        $stripeService->handleWebhook($this->payload);
    }
}
