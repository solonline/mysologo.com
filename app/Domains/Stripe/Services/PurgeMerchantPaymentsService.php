<?php

namespace App\Domains\Stripe\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\MerchantPaymentRequest;
use App\Models\StripePaymentIntent;
use App\Models\StripeTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeMerchantPaymentsService
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     except_id: int,
     *     candidates: list<array<string, mixed>>,
     *     purged: int,
     *     reversed_pending_cents: int,
     *     reversed_fees_cents: int,
     *     skipped: int,
     *     balances: list<array<string, mixed>>
     * }
     */
    public function execute(int $exceptId = 30, bool $dryRun = true): array
    {
        $candidates = StripeTransaction::query()
            ->where('type', TransactionType::MERCHANT_PAYMENT->value)
            ->where('id', '!=', $exceptId)
            ->orderBy('id')
            ->get();

        $preview = $candidates->map(fn (StripeTransaction $tx) => [
            'id' => $tx->id,
            'customer_id' => $tx->customer_id,
            'amount' => (int) $tx->amount,
            'net_amount' => (int) $tx->net_amount,
            'platform_fee' => (int) $tx->platform_fee,
            'partner_funding_status' => $tx->partner_funding_status?->value ?? $tx->partner_funding_status,
            'stripe_charge_id' => $tx->stripe_charge_id,
            'pending_ledger' => $this->findPendingPaymentReceived($tx)?->amount,
            'platform_fee_ledger' => $this->findPlatformFeeCredit($tx)?->amount,
        ])->values()->all();

        if ($dryRun) {
            return [
                'dry_run' => true,
                'except_id' => $exceptId,
                'candidates' => $preview,
                'purged' => 0,
                'reversed_pending_cents' => 0,
                'reversed_fees_cents' => 0,
                'skipped' => 0,
                'balances' => $this->balanceSnapshot($candidates),
            ];
        }

        $purged = 0;
        $reversedPending = 0;
        $reversedFees = 0;
        $skipped = 0;

        foreach ($candidates as $transaction) {
            $outcome = $this->purgeOne($transaction);
            $purged += $outcome['purged'] ? 1 : 0;
            $skipped += $outcome['skipped'] ? 1 : 0;
            $reversedPending += $outcome['reversed_pending_cents'];
            $reversedFees += $outcome['reversed_fees_cents'];
        }

        $result = [
            'dry_run' => false,
            'except_id' => $exceptId,
            'candidates' => $preview,
            'purged' => $purged,
            'reversed_pending_cents' => $reversedPending,
            'reversed_fees_cents' => $reversedFees,
            'skipped' => $skipped,
            'balances' => $this->balanceSnapshot($candidates),
        ];

        Log::channel('stripe')->info('stripe_purge_merchant_payments: completed', $result);

        return $result;
    }

    /**
     * @return array{purged: bool, skipped: bool, reversed_pending_cents: int, reversed_fees_cents: int}
     */
    public function purgeOne(StripeTransaction $transaction): array
    {
        if ($transaction->type !== TransactionType::MERCHANT_PAYMENT) {
            return ['purged' => false, 'skipped' => true, 'reversed_pending_cents' => 0, 'reversed_fees_cents' => 0];
        }

        if ($transaction->trashed()) {
            return ['purged' => false, 'skipped' => true, 'reversed_pending_cents' => 0, 'reversed_fees_cents' => 0];
        }

        $reversedPending = 0;
        $reversedFees = 0;

        DB::transaction(function () use ($transaction, &$reversedPending, &$reversedFees): void {
            $pendingEntry = $this->findPendingPaymentReceived($transaction);

            if ($pendingEntry !== null && ! $this->ledgerService->hasPendingCreditReversal($transaction)) {
                $amount = (int) $pendingEntry->amount;
                $this->ledgerService->reversePendingCredit(
                    customerId: $transaction->customer_id,
                    currency: $transaction->currency,
                    amount: $amount,
                    reference: $transaction,
                    description: 'Stripe pending payment purge #'.$transaction->id,
                );
                $reversedPending = $amount;
            }

            $feeEntry = $this->findPlatformFeeCredit($transaction);

            if ($feeEntry !== null && ! $this->ledgerService->hasPlatformFeeReversal($transaction)) {
                $feeAmount = (int) $feeEntry->amount;
                $this->ledgerService->record(
                    customerId: (int) $feeEntry->customer_id,
                    currency: $feeEntry->currency,
                    entryType: LedgerEntryType::MANUAL_DEBIT,
                    amount: $feeAmount,
                    reference: $transaction,
                    description: 'Platform fee reversal #'.$transaction->id,
                    metadata: ['purge' => true],
                );
                $reversedFees = $feeAmount;
            }

            MerchantPaymentRequest::query()
                ->where(function ($query) use ($transaction): void {
                    $query->where('transaction_id', $transaction->id);

                    if (filled($transaction->stripe_payment_intent_id)) {
                        $query->orWhere('stripe_payment_intent_id', $transaction->stripe_payment_intent_id);
                    }
                })
                ->delete();

            StripePaymentIntent::query()
                ->where('stripe_transaction_id', $transaction->id)
                ->update(['stripe_transaction_id' => null]);

            $transaction->delete();
        });

        return [
            'purged' => true,
            'skipped' => false,
            'reversed_pending_cents' => $reversedPending,
            'reversed_fees_cents' => $reversedFees,
        ];
    }

    /**
     * Remove SecurePay payment requests whose linked stripe_transaction was soft-deleted.
     * Useful after a purge that left orphan completed requests visible on the customer dashboard.
     *
     * @return array{deleted_requests: int, payment_intent_ids: list<string>}
     */
    public function cleanupOrphanedPaymentRequests(): array
    {
        $trashedPaymentIntentIds = StripeTransaction::onlyTrashed()
            ->where('type', TransactionType::MERCHANT_PAYMENT->value)
            ->whereNotNull('stripe_payment_intent_id')
            ->pluck('stripe_payment_intent_id')
            ->filter()
            ->unique()
            ->values();

        $trashedTransactionIds = StripeTransaction::onlyTrashed()
            ->where('type', TransactionType::MERCHANT_PAYMENT->value)
            ->pluck('id');

        $query = MerchantPaymentRequest::query()
            ->where(function ($q) use ($trashedPaymentIntentIds, $trashedTransactionIds): void {
                if ($trashedTransactionIds->isNotEmpty()) {
                    $q->orWhereIn('transaction_id', $trashedTransactionIds->all());
                }

                if ($trashedPaymentIntentIds->isNotEmpty()) {
                    $q->orWhereIn('stripe_payment_intent_id', $trashedPaymentIntentIds->all());
                }
            });

        $paymentIntentIds = $query->clone()
            ->whereNotNull('stripe_payment_intent_id')
            ->pluck('stripe_payment_intent_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $deleted = (int) $query->delete();

        Log::channel('stripe')->info('stripe_purge_merchant_payments: orphaned_requests_cleaned', [
            'deleted_requests' => $deleted,
            'payment_intent_ids' => $paymentIntentIds,
        ]);

        return [
            'deleted_requests' => $deleted,
            'payment_intent_ids' => $paymentIntentIds,
        ];
    }

    private function findPendingPaymentReceived(StripeTransaction $transaction): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where('description', 'Stripe payment #'.$transaction->id)
            ->get()
            ->first(function (LedgerEntry $entry): bool {
                $metadata = $entry->metadata ?? [];

                return ($metadata['bucket'] ?? null) === 'pending';
            });
    }

    private function findPlatformFeeCredit(StripeTransaction $transaction): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::MANUAL_CREDIT)
            ->where('description', PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$transaction->id)
            ->first();
    }

    /**
     * @param  Collection<int, StripeTransaction>  $candidates
     * @return list<array<string, mixed>>
     */
    private function balanceSnapshot(Collection $candidates): array
    {
        $customerIds = $candidates->pluck('customer_id')->unique()->values();

        $platformId = Customer::query()->where('type', 'platform')->value('id');
        if ($platformId !== null) {
            $customerIds->push($platformId);
        }

        return CustomerBalance::query()
            ->whereIn('customer_id', $customerIds->unique()->all())
            ->orderBy('customer_id')
            ->get(['customer_id', 'currency', 'wallester_allocated', 'pending'])
            ->map(fn (CustomerBalance $balance) => $balance->toArray())
            ->all();
    }
}
