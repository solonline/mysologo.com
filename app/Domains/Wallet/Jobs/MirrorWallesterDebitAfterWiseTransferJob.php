<?php

namespace App\Domains\Wallet\Jobs;

use App\Domains\Wallet\Services\RailMirrorService;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MirrorWallesterDebitAfterWiseTransferJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 300, 900, 1800];

    public function __construct(
        public int $customerId,
        public int $amountCents,
        public string $currency,
        public string $idempotencyKey,
        public string $description,
    ) {
        $this->onQueue('wallester');
    }

    public function handle(RailMirrorService $railMirror): void
    {
        $customer = Customer::query()->find($this->customerId);

        if ($customer === null) {
            return;
        }

        $railMirror->pullWallesterMirrorToMaster(
            customer: $customer,
            currency: $this->currency,
            amountCents: $this->amountCents,
            idempotencyKey: $this->idempotencyKey,
            description: $this->description,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('wise')->error('wallester_mirror_debit_after_wise_job_failed', [
            'customer_id' => $this->customerId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'idempotency_key' => $this->idempotencyKey,
            'message' => $exception?->getMessage(),
        ]);
    }
}
