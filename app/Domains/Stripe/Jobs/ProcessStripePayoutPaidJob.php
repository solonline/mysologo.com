<?php

namespace App\Domains\Stripe\Jobs;

use App\Domains\Wallet\Services\StripePayoutSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessStripePayoutPaidJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 3600;

    public function __construct(
        public string $payoutId,
        public string $currency = 'eur',
        public bool $livemode = true,
    ) {
        $this->onQueue('stripe-webhooks');
    }

    public function uniqueId(): string
    {
        return $this->payoutId;
    }

    public function handle(StripePayoutSettlementService $settlement): void
    {
        $settlement->settlePayout($this->payoutId, $this->currency, $this->livemode);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('stripe')->error('ProcessStripePayoutPaidJob failed', [
            'payout_id' => $this->payoutId,
            'currency' => $this->currency,
            'livemode' => $this->livemode,
            'message' => $exception?->getMessage(),
        ]);
    }
}
