<?php

namespace App\Jobs;

use App\Models\MerchantPaymentRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchMerchantCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public MerchantPaymentRequest $paymentRequest) {}

    public function handle(): void
    {
        $callbackUrl = $this->paymentRequest->callback_url
            ?? $this->paymentRequest->merchant->webhook_url;

        if (! $callbackUrl) {
            return;
        }

        $payload = [
            'uuid'               => $this->paymentRequest->uuid,
            'status'             => $this->paymentRequest->status,
            'external_reference' => $this->paymentRequest->external_reference,
            'amount'             => $this->paymentRequest->amount,
            'currency'           => $this->paymentRequest->currency,
            'paid_at'            => $this->paymentRequest->paid_at?->toIso8601String(),
        ];

        $response = Http::timeout(10)->post($callbackUrl, $payload);

        if (! $response->successful()) {
            Log::warning('DispatchMerchantCallback: callback falhou', [
                'payment_request_uuid' => $this->paymentRequest->uuid,
                'callback_url'         => $callbackUrl,
                'status'               => $response->status(),
            ]);

            $this->release(60);
        }
    }
}
