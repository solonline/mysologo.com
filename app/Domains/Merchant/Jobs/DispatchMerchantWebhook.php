<?php

namespace App\Domains\Merchant\Jobs;

use App\Domains\Merchant\Services\MerchantWebhookSigner;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Models\MerchantPaymentRequest;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DispatchMerchantWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 14400];

    public function __construct(public MerchantPaymentRequest $paymentRequest) {}

    public function handle(MerchantWebhookSigner $signer): void
    {
        $this->paymentRequest->loadMissing(['merchant', 'stripeTransaction']);

        $webhookUrl = $this->paymentRequest->webhook_url
            ?? $this->paymentRequest->merchant?->webhook_url;

        if (! $webhookUrl) {
            return;
        }

        $payload = [
            'id' => 'evt_'.$this->paymentRequest->uuid,
            'type' => 'payment_request.'.$this->paymentRequest->status,
            'created' => now()->timestamp,
            'data' => $this->buildDataPayload(),
        ];

        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $deliveryId = (string) Str::uuid();
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            MerchantWebhookSigner::HEADER_DELIVERY_ID => $deliveryId,
        ];

        $rawSecret = $this->paymentRequest->merchant
            ? $signer->resolveRawSecret($this->paymentRequest->merchant)
            : null;

        if ($rawSecret !== null) {
            $signed = $signer->sign($rawSecret, $rawBody);
            $headers[MerchantWebhookSigner::HEADER_TIMESTAMP] = (string) $signed['timestamp'];
            $headers[MerchantWebhookSigner::HEADER_SIGNATURE] = $signed['signature'];
        } else {
            Log::channel('merchant')->warning('DispatchMerchantWebhook: merchant sem webhook_secret — a enviar sem assinatura', [
                'payment_request_uuid' => $this->paymentRequest->uuid,
                'merchant_id' => $this->paymentRequest->merchant_id,
            ]);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->withBody($rawBody, 'application/json')
                ->post($webhookUrl);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'DispatchMerchantWebhook: falha de rede — '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        Log::channel('merchant')->warning('DispatchMerchantWebhook: entrega falhou', [
            'payment_request_uuid' => $this->paymentRequest->uuid,
            'webhook_url' => $webhookUrl,
            'status' => $status,
            'delivery_id' => $deliveryId,
            'attempt' => $this->attempts(),
        ]);

        // 4xx (exceto 408/429) = erro permanente do lado do merchant
        if ($status >= 400 && $status < 500 && ! in_array($status, [408, 429], true)) {
            return;
        }

        throw new RuntimeException("DispatchMerchantWebhook: HTTP {$status}");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDataPayload(): array
    {
        $tx = $this->paymentRequest->stripeTransaction;
        $isCompleted = $this->paymentRequest->status === 'completed';
        $fundsAvailableAt = $this->resolveFundsAvailableAt($tx);
        $fundsAvailable = $this->resolveFundsAvailable($tx, $fundsAvailableAt);

        return [
            'uuid' => $this->paymentRequest->uuid,
            'status' => $this->paymentRequest->status,
            'settlement_status' => $isCompleted
                ? ($fundsAvailable ? 'available' : 'pending')
                : null,
            'funds_available' => $fundsAvailable,
            'external_reference' => $this->paymentRequest->external_reference,
            'amount' => $this->paymentRequest->amount,
            'fee_total' => $tx?->resolvedFeeTotal(),
            'net_amount' => $tx?->resolvedNetAmount(),
            'currency' => $this->paymentRequest->currency,
            'paid_at' => $this->paymentRequest->paid_at?->toIso8601String(),
            'funds_available_at' => $fundsAvailableAt,
        ];
    }

    private function resolveFundsAvailableAt(?StripeTransaction $tx): ?string
    {
        if (! $tx?->available_on) {
            return null;
        }

        return $tx->available_on->toDateString();
    }

    private function resolveFundsAvailable(?StripeTransaction $tx, ?string $fundsAvailableAt): bool
    {
        if (! $tx instanceof StripeTransaction) {
            return false;
        }

        if ($tx->balance_settled_at !== null) {
            return true;
        }

        if ($tx->partner_funding_status === PartnerFundingStatus::Completed) {
            return true;
        }

        if ($fundsAvailableAt === null) {
            return false;
        }

        return now()->startOfDay()->greaterThanOrEqualTo(
            $tx->available_on->copy()->startOfDay(),
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('merchant')->error('DispatchMerchantWebhook: esgotaram-se as tentativas', [
            'payment_request_uuid' => $this->paymentRequest->uuid,
            'merchant_id' => $this->paymentRequest->merchant_id,
            'message' => $exception?->getMessage(),
        ]);
    }
}
