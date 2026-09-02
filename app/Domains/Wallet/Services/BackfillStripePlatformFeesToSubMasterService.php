<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Support\Str;
use Throwable;

class BackfillStripePlatformFeesToSubMasterService
{
    public const PENDING_LIQUIDITY = 'liquidity';

    public const PENDING_STRIPE_PAYOUT = 'stripe_payout';

    public const PENDING_STRIPE_BALANCE = 'stripe_balance';

    public function __construct(
        protected FundingPartnerMovementService $movements,
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}

    /**
     * @return array{
     *     landed_cents: int,
     *     pending_liquidity_cents: int,
     *     pending_stripe_cents: int,
     *     fx_already_cents: int,
     *     master_available_cents: int,
     *     liquidity_shortfall_cents: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function execute(bool $dryRun = false): array
    {
        $feeAccount = $this->wallesterDriver->getPlatformFeeAccount('EUR');
        $master = $this->wallesterDriver->getPlatformMasterAccount('EUR');
        $masterCustomer = $master->customer;
        $masterAvailable = $masterCustomer instanceof Customer
            ? $this->wallesterDriver->getAvailableBalance($masterCustomer, 'EUR')
            : (int) ($master->available_amount ?? 0);
        $fxAlreadyCents = $this->completedFxOnFeeAccountCents($feeAccount);

        $transactions = StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where('platform_fee', '>', 0)
            ->with('customer')
            ->orderBy('id')
            ->get();

        $rows = [];
        $landed = 0;
        $pendingLiquidity = 0;
        $pendingStripe = 0;

        foreach ($transactions as $transaction) {
            $feeCents = (int) $transaction->platform_fee;
            $bucket = $this->pendingBucket($transaction);
            $alreadyLanded = $this->hasCompletedFeeIn($transaction);

            if ($this->fundingPolicy->isBeforeAccountingCutoff($transaction)) {
                if (! $dryRun) {
                    $this->deletePendingFeeIn($transaction);
                }
                $rows[] = $this->row($transaction, $feeCents, 'ignored_cutoff', $bucket);

                continue;
            }

            if ($alreadyLanded) {
                $landed += $feeCents;
                $rows[] = $this->row($transaction, $feeCents, 'already_on_sub', $bucket);

                continue;
            }

            if ($dryRun) {
                if ($bucket === self::PENDING_LIQUIDITY) {
                    $pendingLiquidity += $feeCents;
                    $status = $masterAvailable >= $feeCents ? 'will_land' : 'pending_liquidity';
                    if ($status === 'will_land') {
                        $landed += $feeCents;
                        $pendingLiquidity -= $feeCents;
                    }
                } else {
                    $pendingStripe += $feeCents;
                    $status = $bucket === self::PENDING_STRIPE_PAYOUT
                        ? 'pending_stripe_payout'
                        : 'pending_stripe_balance';
                }
                $rows[] = $this->row($transaction, $feeCents, $status, $bucket);

                continue;
            }

            $this->ensurePendingFeeIn($transaction, $feeAccount, $master, $bucket);

            if ($bucket !== self::PENDING_LIQUIDITY) {
                $pendingStripe += $feeCents;
                $rows[] = $this->row(
                    $transaction,
                    $feeCents,
                    $bucket === self::PENDING_STRIPE_PAYOUT ? 'pending_stripe_payout' : 'pending_stripe_balance',
                    $bucket,
                );

                continue;
            }

            try {
                $this->movements->transferFromMasterToPlatformFees(
                    amountCents: $feeCents,
                    currency: strtoupper((string) $transaction->currency),
                    description: 'Taxa Yoop',
                    idempotencyKey: 'stripe-platform-fee:'.$transaction->id,
                    reference: $transaction,
                );

                if ($this->hasCompletedFeeIn($transaction)) {
                    $landed += $feeCents;
                    $masterAvailable = max(0, $masterAvailable - $feeCents);
                    $rows[] = $this->row($transaction, $feeCents, 'landed', $bucket);
                } else {
                    $pendingLiquidity += $feeCents;
                    $rows[] = $this->row($transaction, $feeCents, 'pending_liquidity', $bucket);
                }
            } catch (Throwable $exception) {
                $pendingLiquidity += $feeCents;
                $this->markPendingReason($transaction, $bucket, $exception->getMessage());
                $rows[] = $this->row($transaction, $feeCents, 'pending_liquidity', $bucket, $exception->getMessage());
            }
        }

        $masterAvailableAfter = $masterCustomer instanceof Customer
            ? (int) ($master->fresh()->available_amount ?? $masterAvailable)
            : $masterAvailable;

        return [
            'landed_cents' => $landed,
            'pending_liquidity_cents' => $pendingLiquidity,
            'pending_stripe_cents' => $pendingStripe,
            'fx_already_cents' => $fxAlreadyCents,
            'master_available_cents' => $masterAvailableAfter,
            'liquidity_shortfall_cents' => max(0, $pendingLiquidity - $masterAvailableAfter),
            'rows' => $rows,
        ];
    }

    private function pendingBucket(StripeTransaction $transaction): string
    {
        $status = $transaction->partner_funding_status;

        if ($status === PartnerFundingStatus::Completed || $transaction->partner_funded_at !== null) {
            return self::PENDING_LIQUIDITY;
        }

        if ($status === PartnerFundingStatus::AwaitingStripePayout) {
            return self::PENDING_STRIPE_PAYOUT;
        }

        return self::PENDING_STRIPE_BALANCE;
    }

    private function completedFxOnFeeAccountCents(CustomerWallesterAccount $feeAccount): int
    {
        return (int) FundingPartnerMovement::query()
            ->where('funding_account_id', $feeAccount->id)
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->where('idempotency_key', 'like', 'fx-platform:%:in')
            ->sum('amount');
    }

    private function hasCompletedFeeIn(StripeTransaction $transaction): bool
    {
        return FundingPartnerMovement::query()
            ->where('idempotency_key', 'stripe-platform-fee:'.$transaction->id.':in')
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->exists();
    }

    private function deletePendingFeeIn(StripeTransaction $transaction): void
    {
        FundingPartnerMovement::query()
            ->where('idempotency_key', 'stripe-platform-fee:'.$transaction->id.':in')
            ->where('status', FundingPartnerMovementStatus::Pending)
            ->delete();
    }

    private function ensurePendingFeeIn(
        StripeTransaction $transaction,
        CustomerWallesterAccount $feeAccount,
        CustomerWallesterAccount $master,
        string $bucket,
    ): void {
        $inKey = 'stripe-platform-fee:'.$transaction->id.':in';

        $existing = FundingPartnerMovement::query()->where('idempotency_key', $inKey)->first();

        if ($existing instanceof FundingPartnerMovement) {
            if ($existing->status !== FundingPartnerMovementStatus::Completed) {
                $existing->update([
                    'funding_account_id' => $feeAccount->id,
                    'external_account_id' => $feeAccount->wallester_account_id,
                    'status' => FundingPartnerMovementStatus::Pending,
                    'metadata' => array_merge(is_array($existing->metadata) ? $existing->metadata : [], [
                        'pending_reason' => $bucket,
                    ]),
                ]);
            }

            return;
        }

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => CustomerWallesterAccount::class,
            'funding_account_id' => $feeAccount->id,
            'external_account_id' => $feeAccount->wallester_account_id,
            'counterparty_external_account_id' => $master->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => (int) $transaction->platform_fee,
            'currency' => strtoupper((string) $transaction->currency),
            'status' => FundingPartnerMovementStatus::Pending,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $inKey,
            'description' => 'Taxa Yoop',
            'transfer_group_id' => (string) Str::uuid(),
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'metadata' => ['pending_reason' => $bucket],
        ]);
    }

    private function markPendingReason(StripeTransaction $transaction, string $bucket, string $message): void
    {
        $movement = FundingPartnerMovement::query()
            ->where('idempotency_key', 'stripe-platform-fee:'.$transaction->id.':in')
            ->first();

        if (! $movement instanceof FundingPartnerMovement) {
            return;
        }

        $movement->update([
            'status' => FundingPartnerMovementStatus::Pending,
            'failure_reason' => $message,
            'metadata' => array_merge(is_array($movement->metadata) ? $movement->metadata : [], [
                'pending_reason' => $bucket,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        StripeTransaction $transaction,
        int $feeCents,
        string $status,
        string $bucket,
        ?string $note = null,
    ): array {
        return [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type?->value ?? (string) $transaction->type,
            'customer' => $transaction->customer?->name,
            'fee_cents' => $feeCents,
            'funding_status' => $transaction->partner_funding_status?->value,
            'bucket' => $bucket,
            'status' => $status,
            'note' => $note,
        ];
    }
}
