<?php

namespace App\Domains\Wallester\Jobs;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWallesterAccountMovementsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?int $customerWallesterAccountId = null,
    ) {
        $this->onQueue('wallester');
    }

    public function handle(
        WallesterGatewayInterface $gateway,
        FundingPartnerMovementService $movements,
        WallesterFundingTransferDriver $wallesterDriver,
    ): void {
        $query = CustomerWallesterAccount::query()
            ->where('status', IntegrationAccountStatus::ACTIVE);

        if ($this->customerWallesterAccountId !== null) {
            $query->whereKey($this->customerWallesterAccountId);
        }

        $query->each(function (CustomerWallesterAccount $account) use ($gateway, $movements, $wallesterDriver): void {
            $this->syncAccount($account, $gateway, $movements, $wallesterDriver);
        });
    }

    private function syncAccount(
        CustomerWallesterAccount $account,
        WallesterGatewayInterface $gateway,
        FundingPartnerMovementService $movements,
        WallesterFundingTransferDriver $wallesterDriver,
    ): void {
        $response = $gateway->forCurrency((string) ($account->funding_currency ?? 'EUR'))->listAccountTransactions($account->wallester_account_id, [
            'from_record' => 0,
            'records_count' => 100,
        ], (string) $account->customer_id);

        foreach ($response['transactions'] as $transaction) {
            $this->importTransaction($account, $transaction, $movements, $wallesterDriver);
        }
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function importTransaction(
        CustomerWallesterAccount $account,
        array $transaction,
        FundingPartnerMovementService $movements,
        WallesterFundingTransferDriver $wallesterDriver,
    ): void {
        $externalId = (string) ($transaction['id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $type = strtolower((string) ($transaction['type'] ?? ''));
        $status = strtolower((string) ($transaction['status'] ?? ''));

        if ($status !== '' && ! in_array($status, ['cleared', 'completed', 'settled'], true)) {
            return;
        }

        $amountCents = $wallesterDriver->amountToCents($transaction['account_amount'] ?? $transaction['amount'] ?? 0);

        if ($amountCents <= 0) {
            return;
        }

        $isRefund = str_contains($type, 'refund') || str_contains($type, 'reversal');
        $movementType = $isRefund
            ? FundingPartnerMovementType::CardRefund
            : FundingPartnerMovementType::CardPurchase;
        $direction = $isRefund
            ? FundingPartnerMovementDirection::In
            : FundingPartnerMovementDirection::Out;

        if (! in_array($type, ['purchase', 'card_payment', 'authorization', 'refund', 'card_refund', 'reversal'], true)
            && ! str_contains($type, 'card')) {
            return;
        }

        $card = null;
        $cardId = $transaction['card_id'] ?? null;

        if (is_string($cardId) && $cardId !== '') {
            $card = CustomerWallesterCard::query()
                ->where('wallester_card_id', $cardId)
                ->first();
        }

        try {
            $movements->recordCardMovement(
                account: $account,
                type: $movementType,
                direction: $direction,
                amountCents: $amountCents,
                currency: (string) ($transaction['account_currency_code'] ?? $transaction['currency_code'] ?? 'EUR'),
                partnerExternalId: $externalId,
                source: FundingPartnerMovementSource::Sync,
                card: $card,
                description: is_string($transaction['merchant_name'] ?? null) ? $transaction['merchant_name'] : null,
                metadata: $transaction,
            );
        } catch (\Throwable $exception) {
            Log::channel('wallester')->warning('wallester_sync_movement_import_failed', [
                'account_id' => $account->id,
                'external_id' => $externalId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
