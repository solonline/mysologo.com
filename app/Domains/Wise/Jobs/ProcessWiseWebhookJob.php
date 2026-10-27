<?php

namespace App\Domains\Wise\Jobs;

use App\Domains\Wise\Services\WiseWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWiseWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public ?string $deliveryId = null,
    ) {
        $this->onQueue('wise');
    }

    public function handle(WiseWebhookProcessor $processor): void
    {
        $processor->process($this->payload, $this->deliveryId);
    }
}
