<?php

namespace App\Domains\Wise\Services;

use App\Models\WiseLiquidityEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class WiseWebhookProcessor
{
    public function __construct(
        private readonly WiseTransferSettlementService $settlements,
    ) {}
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload, ?string $deliveryId = null): void
    {
        $eventType = (string) ($payload['event_type'] ?? $payload['eventType'] ?? '');

        if ($eventType === '') {
            Log::channel('wise')->info('wise_webhook_ignored_missing_event_type', [
                'delivery_id' => $deliveryId,
            ]);

            return;
        }

        if ($deliveryId !== null && $deliveryId !== '') {
            $existing = WiseLiquidityEvent::query()->where('delivery_id', $deliveryId)->first();

            if ($existing instanceof WiseLiquidityEvent) {
                return;
            }
        }

        match (true) {
            $eventType === 'balances#update' => $this->recordBalanceUpdate($payload, $deliveryId, $eventType),
            $eventType === 'transfers#state-change' => $this->settlements->applyStateChange($payload),
            str_starts_with($eventType, 'incoming-transfer') => $this->recordIncomingTransfer($payload, $deliveryId, $eventType),
            default => Log::channel('wise')->info('wise_webhook_unhandled_event', [
                'event_type' => $eventType,
                'delivery_id' => $deliveryId,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordBalanceUpdate(array $payload, ?string $deliveryId, string $eventType): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $amount = $data['amount'] ?? 0;
        $currency = isset($data['currency']) ? strtoupper((string) $data['currency']) : null;
        $amountCents = (int) round(((float) $amount) * 100);

        $this->storeEvent(
            eventType: $eventType,
            deliveryId: $deliveryId,
            balanceId: isset($data['balance_id']) ? (int) $data['balance_id'] : null,
            currency: $currency,
            amountCents: $amountCents,
            postTransactionBalance: isset($data['post_transaction_balance_amount'])
                ? (float) $data['post_transaction_balance_amount']
                : null,
            channelName: isset($data['channel_name']) ? (string) $data['channel_name'] : null,
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordIncomingTransfer(array $payload, ?string $deliveryId, string $eventType): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];

        $amount = $data['amount'] ?? $resource['amount'] ?? 0;
        $currency = $data['currency'] ?? $resource['currency'] ?? null;
        $currency = is_string($currency) ? strtoupper($currency) : null;
        $amountCents = (int) round(((float) $amount) * 100);

        $this->storeEvent(
            eventType: $eventType,
            deliveryId: $deliveryId,
            balanceId: isset($data['balance_id']) ? (int) $data['balance_id'] : null,
            currency: $currency,
            amountCents: $amountCents,
            postTransactionBalance: null,
            channelName: isset($data['channel_name']) ? (string) $data['channel_name'] : 'incoming-transfer',
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeEvent(
        string $eventType,
        ?string $deliveryId,
        ?int $balanceId,
        ?string $currency,
        int $amountCents,
        ?float $postTransactionBalance,
        ?string $channelName,
        ?string $occurredAt,
        array $payload,
    ): void {
        try {
            WiseLiquidityEvent::query()->create([
                'event_type' => $eventType,
                'delivery_id' => $deliveryId !== null && $deliveryId !== '' ? $deliveryId : null,
                'balance_id' => $balanceId,
                'currency' => $currency,
                'amount_cents' => $amountCents,
                'post_transaction_balance' => $postTransactionBalance,
                'channel_name' => $channelName,
                'occurred_at' => $occurredAt,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('wise')->error('wise_liquidity_event_store_failed', [
                'event_type' => $eventType,
                'delivery_id' => $deliveryId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
