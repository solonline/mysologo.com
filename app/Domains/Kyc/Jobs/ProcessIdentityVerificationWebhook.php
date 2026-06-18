<?php

namespace App\Domains\Kyc\Jobs;

use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIdentityVerificationWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $payload) {}

    public function handle(IdentityVerificationWebhookService $webhookService): void
    {
        $webhookService->handleDataUpdated($this->payload);
    }
}
