<?php

namespace App\Domains\Wise\Services;

use App\Domains\Customer\Notifications\NotificationDispatcher;
use App\Domains\Customer\Notifications\TransferSentNotification;
use App\Domains\Wise\Enums\WiseTransferStatus;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Models\WiseTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WiseTransferSettlementService
{
    public function __construct(
        private readonly RailMirrorService $railMirror,
        private readonly FundingPartnerMovementService $movements,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyStateChange(array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];
        $transferId = $resource['id'] ?? $data['resource_id'] ?? null;
        $currentState = $data['current_state'] ?? $data['currentState'] ?? null;

        if (! is_numeric($transferId) || ! is_string($currentState) || $currentState === '') {
            Log::channel('wise')->info('wise_transfer_state_change_ignored', [
                'transfer_id' => $transferId,
                'current_state' => $currentState,
            ]);

            return;
        }

        $transfer = WiseTransfer::query()
            ->where('wise_transfer_id', (int) $transferId)
            ->first();

        if (! $transfer instanceof WiseTransfer) {
            return;
        }

        $state = WiseTransferStatus::tryFrom(strtolower($currentState));

        if (! $state instanceof WiseTransferStatus) {
            Log::channel('wise')->info('wise_transfer_state_change_ignored', [
                'transfer_id' => $transferId,
                'current_state' => $currentState,
            ]);

            return;
        }

        if ($state->returnsFunds()) {
            $this->fail($transfer, $state);

            return;
        }

        if ($state === WiseTransferStatus::OutgoingPaymentSent) {
            $this->settle($transfer, $state);

            return;
        }

        $transfer->status = $state;
        $transfer->save();
    }

    private function settle(WiseTransfer $transfer, WiseTransferStatus $state): void
    {
        $customer = null;
        $shouldMoveFunds = false;
        $shouldSendReceipt = false;

        DB::transaction(function () use ($transfer, $state, &$customer, &$shouldMoveFunds, &$shouldSendReceipt): void {
            $row = WiseTransfer::query()->lockForUpdate()->find($transfer->id);

            if (! $row instanceof WiseTransfer || $row->refunded_at !== null) {
                return;
            }

            $row->status = $state;

            if ($row->settled_at === null) {
                $row->settled_at = now();
                $shouldMoveFunds = true;
            }

            if ($row->receipt_sent_at === null) {
                $row->receipt_sent_at = now();
                $shouldSendReceipt = true;
            }

            $row->save();
            $customer = $row->customer()->first();
            $transfer->setRawAttributes($row->getAttributes(), true);
            $transfer->exists = true;
        });

        if ($customer === null) {
            return;
        }

        if ($shouldMoveFunds) {
            $this->railMirror->dispatchWallesterMirrorDebit(
                customer: $customer,
                currency: $transfer->currency,
                amountCents: (int) $transfer->amount_cents,
                idempotencyKey: $transfer->idempotency_key,
                description: 'Wise transfer mirror #'.$transfer->wise_transfer_id,
            );
        }

        if (! $shouldSendReceipt) {
            return;
        }

        $amountLabel = number_format(((int) $transfer->amount_cents) / 100, 2, ',', '.').' '.$transfer->currency;

        $this->notifications->send(
            $customer,
            new TransferSentNotification(
                recipient: $transfer->beneficiary_name,
                amountLabel: $amountLabel,
                description: $transfer->reference,
                movementId: (int) $transfer->wise_transfer_id,
                extra: [
                    'amount_cents' => (int) $transfer->amount_cents,
                    'currency' => $transfer->currency,
                    'amount_label' => $amountLabel,
                    'recipient' => $transfer->beneficiary_name,
                    'wise_transfer_id' => (int) $transfer->wise_transfer_id,
                ],
            ),
        );
    }

    private function fail(WiseTransfer $transfer, WiseTransferStatus $state): void
    {
        $shouldReturnCardFunds = false;
        $customer = null;

        DB::transaction(function () use ($transfer, $state, &$shouldReturnCardFunds, &$customer): void {
            $row = WiseTransfer::query()->lockForUpdate()->find($transfer->id);

            if (! $row instanceof WiseTransfer || $row->refunded_at !== null) {
                return;
            }

            $customer = $row->customer()->first();
            $shouldReturnCardFunds = $row->settled_at !== null;

            if ($customer !== null && ! $shouldReturnCardFunds) {
                $this->railMirror->releaseWiseTransferReservation(
                    customer: $customer,
                    currency: $row->currency,
                    amountCents: (int) $row->amount_cents,
                    idempotencyKey: $row->idempotency_key,
                    reference: $row,
                );
                $row->refunded_at = now();
            }

            $row->status = $state;
            $row->save();
            $transfer->setRawAttributes($row->getAttributes(), true);
            $transfer->exists = true;
            $transfer->setRelation('customer', $customer);
        });

        if (! $shouldReturnCardFunds || $customer === null) {
            return;
        }

        $this->movements->transferFromMasterToCustomer(
            to: $customer,
            amountCents: (int) $transfer->amount_cents,
            currency: $transfer->currency,
            description: 'Wise transfer refund #'.$transfer->wise_transfer_id,
            idempotencyKey: $transfer->idempotency_key.':refund',
            reference: $transfer,
        );

        $this->railMirror->releaseWiseTransferReservation(
            customer: $customer,
            currency: $transfer->currency,
            amountCents: (int) $transfer->amount_cents,
            idempotencyKey: $transfer->idempotency_key,
            reference: $transfer,
        );

        WiseTransfer::query()->whereKey($transfer->id)->whereNull('refunded_at')->update([
            'refunded_at' => now(),
        ]);
    }
}
