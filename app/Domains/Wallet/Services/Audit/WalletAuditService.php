<?php

namespace App\Domains\Wallet\Services\Audit;

use App\Domains\Wallet\DTOs\AuditFinding;
use App\Domains\Wallet\DTOs\AuditReport;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class WalletAuditService
{
    private const PARTNER_FUNDING_STALE_MINUTES = 15;

    public function __construct(
        protected LedgerService $ledgerService,
        protected WiseGatewayInterface $wiseGateway,
    ) {}

    public function run(
        ?string $from = null,
        ?string $to = null,
        ?int $customerId = null,
        ?string $currency = null,
    ): AuditReport {
        $findings = [
            ...$this->checkFeeIntegrity($from, $to, $customerId, $currency),
            ...$this->checkUncalculatedFees($from, $to, $customerId, $currency),
            ...$this->checkSucceededPaymentsMissingLedger($from, $to, $customerId, $currency),
            ...$this->checkUnsettledSucceededPayments($from, $to, $customerId, $currency),
            ...$this->checkWalletVsExpectedAvailable($customerId, $currency),
            ...$this->checkStalePendingBalances($customerId, $currency),
            ...$this->checkBalanceConsistency($customerId, $currency),
            ...$this->checkTopupWallesterMovements($from, $to, $customerId, $currency),
            ...$this->checkMissingWallesterTopups($from, $to, $customerId, $currency),
            ...$this->checkPartnerFundingStatuses($from, $to, $customerId, $currency),
            ...$this->checkWiseAllocationMatchesLedger($customerId, $currency),
            ...$this->checkWallesterMirrorVsWiseAllocation($customerId, $currency),
            ...$this->checkWiseMasterVsAllocations($currency),
        ];

        return new AuditReport($from, $to, $customerId, $currency, $findings);
    }

    /** @return array{transactions: int, balances: int, ledger_entries: int} */
    public function scanStats(
        ?string $from = null,
        ?string $to = null,
        ?int $customerId = null,
        ?string $currency = null,
    ): array {
        $txQuery = $this->transactionQuery($from, $to, $customerId, $currency);

        $balanceQuery = CustomerBalance::query();
        if ($customerId !== null) {
            $balanceQuery->where('customer_id', $customerId);
        }
        if ($currency !== null) {
            $balanceQuery->where('currency', strtoupper($currency));
        }

        $ledgerQuery = LedgerEntry::query();
        if ($customerId !== null) {
            $ledgerQuery->where('customer_id', $customerId);
        }
        if ($currency !== null) {
            $ledgerQuery->where('currency', strtoupper($currency));
        }

        return [
            'transactions' => (int) $txQuery->count(),
            'balances' => (int) $balanceQuery->count(),
            'ledger_entries' => (int) $ledgerQuery->count(),
        ];
    }

    /** @return list<AuditFinding> */
    private function checkFeeIntegrity(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('amount', '>', 0)
            ->whereNotIn('status', ['failed', 'canceled', 'cancelled'])
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                $expected = (int) $transaction->stripe_fee + (int) $transaction->platform_fee + (int) $transaction->net_amount;

                if ((int) $transaction->amount !== $expected) {
                    $findings[] = new AuditFinding(
                        severity: 'critical',
                        code: 'FEE_SUM_MISMATCH',
                        message: 'Transaction amount does not equal stripe_fee + platform_fee + net_amount.',
                        context: [
                            'transaction_id' => $transaction->id,
                            'customer_id' => $transaction->customer_id,
                            'status' => $transaction->status,
                            'amount' => (int) $transaction->amount,
                            'stripe_fee' => (int) $transaction->stripe_fee,
                            'platform_fee' => (int) $transaction->platform_fee,
                            'net_amount' => (int) $transaction->net_amount,
                            'expected_amount' => $expected,
                        ],
                    );
                }

                if ($transaction->stripe_payment_method_fee_id === null) {
                    return;
                }

                $feeConfig = StripePaymentMethodFee::query()->find($transaction->stripe_payment_method_fee_id);

                if ($feeConfig === null) {
                    return;
                }

                $expectedPlatformFee = $feeConfig->calculatePlatformFee((int) $transaction->amount);

                if ((int) $transaction->platform_fee !== $expectedPlatformFee) {
                    // Config may have changed after the payment — do not flag historical fees.
                    if (
                        $feeConfig->updated_at !== null
                        && $transaction->created_at !== null
                        && $feeConfig->updated_at->gt($transaction->created_at)
                    ) {
                        return;
                    }

                    $findings[] = new AuditFinding(
                        severity: 'warning',
                        code: 'PLATFORM_FEE_DRIFT',
                        message: 'Recorded platform_fee does not match fee configuration.',
                        context: [
                            'transaction_id' => $transaction->id,
                            'customer_id' => $transaction->customer_id,
                            'platform_fee' => (int) $transaction->platform_fee,
                            'expected_platform_fee' => $expectedPlatformFee,
                            'fee_config_id' => $feeConfig->id,
                        ],
                    );
                }
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkUncalculatedFees(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('status', 'succeeded')
            ->where('amount', '>', 0)
            ->where('net_amount', '<=', 0)
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'UNCALCULATED_FEES',
                    message: 'Succeeded transaction has gross amount but net_amount is zero (charge.updated fees missing).',
                    context: [
                        'transaction_id' => $transaction->id,
                        'customer_id' => $transaction->customer_id,
                        'amount' => (int) $transaction->amount,
                        'stripe_fee' => (int) $transaction->stripe_fee,
                        'platform_fee' => (int) $transaction->platform_fee,
                        'net_amount' => (int) $transaction->net_amount,
                    ],
                );
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkSucceededPaymentsMissingLedger(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('status', 'succeeded')
            ->where('net_amount', '>', 0)
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                if ($this->hasPaymentLedgerEntry($transaction)) {
                    return;
                }

                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'MISSING_PAYMENT_LEDGER',
                    message: 'Succeeded transaction with net_amount has no PAYMENT_RECEIVED ledger entry.',
                    context: [
                        'transaction_id' => $transaction->id,
                        'customer_id' => $transaction->customer_id,
                        'net_amount' => (int) $transaction->net_amount,
                        'balance_settled_at' => $transaction->balance_settled_at?->toIso8601String(),
                    ],
                );
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkUnsettledSucceededPayments(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('status', 'succeeded')
            ->where('net_amount', '>', 0)
            ->whereNull('balance_settled_at')
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                // Already in available or intentionally held in pending — not a missing credit.
                if ($this->hasAvailableWalletCredit($transaction) || $this->hasPendingWalletCredit($transaction)) {
                    return;
                }

                $findings[] = new AuditFinding(
                    severity: 'warning',
                    code: 'UNSETTLED_WALLET_CREDIT',
                    message: 'Succeeded transaction was never credited to wallet (balance_settled_at is null).',
                    context: [
                        'transaction_id' => $transaction->id,
                        'customer_id' => $transaction->customer_id,
                        'net_amount' => (int) $transaction->net_amount,
                    ],
                );
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkWalletVsExpectedAvailable(?int $customerId, ?string $currency): array
    {
        $findings = [];

        foreach ($this->customerCurrencyPairs($customerId, $currency) as $pair) {
            $customerIdValue = (int) $pair['customer_id'];
            $currencyValue = strtoupper((string) $pair['currency']);

            $expectedFromSettledTx = (int) StripeTransaction::query()
                ->where('customer_id', $customerIdValue)
                ->where('currency', $currencyValue)
                ->where('direction', TransactionDirection::Credit->value)
                ->whereNull('related_transaction_id')
                ->where('status', 'succeeded')
                ->whereNotNull('balance_settled_at')
                ->sum('net_amount');

            $balance = CustomerBalance::query()
                ->where('customer_id', $customerIdValue)
                ->where('currency', $currencyValue)
                ->first();

            $available = (int) ($balance?->wallester_allocated ?? 0);
            $pending = (int) ($balance?->pending ?? 0);

            $ledgerEntryCount = LedgerEntry::query()
                ->where('customer_id', $customerIdValue)
                ->where('currency', $currencyValue)
                ->count();

            // LEDGER_BALANCE_MISMATCH is emitted solely by checkBalanceConsistency to avoid duplicates.

            if ($expectedFromSettledTx > 0 && $available !== $expectedFromSettledTx && $ledgerEntryCount === 0) {
                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'WALLET_AVAILABLE_MISMATCH',
                    message: 'Wallet available does not match sum of settled transaction net amounts.',
                    context: [
                        'customer_id' => $customerIdValue,
                        'currency' => $currencyValue,
                        'wallester_allocated' => $available,
                        'pending' => $pending,
                        'expected_from_settled_transactions' => $expectedFromSettledTx,
                    ],
                );
            }

            $uncreditedNet = (int) StripeTransaction::query()
                ->where('customer_id', $customerIdValue)
                ->where('currency', $currencyValue)
                ->where('direction', TransactionDirection::Credit->value)
                ->whereNull('related_transaction_id')
                ->where('status', 'succeeded')
                ->where('net_amount', '>', 0)
                ->whereNull('balance_settled_at')
                ->sum('net_amount');

            if ($uncreditedNet > 0 && $available < $uncreditedNet) {
                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'UNCREDITED_NET_AMOUNT',
                    message: 'Succeeded transactions exist whose net_amount was never reflected in wallet available.',
                    context: [
                        'customer_id' => $customerIdValue,
                        'currency' => $currencyValue,
                        'wallester_allocated' => $available,
                        'pending' => $pending,
                        'uncredited_net_total' => $uncreditedNet,
                    ],
                );
            }
        }

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkStalePendingBalances(?int $customerId, ?string $currency): array
    {
        $findings = [];

        $query = CustomerBalance::query()->where('pending', '>', 0);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        $query->each(function (CustomerBalance $balance) use (&$findings): void {
            $explainedPending = $this->explainedPendingCents(
                (int) $balance->customer_id,
                strtoupper($balance->currency),
            );

            if ((int) $balance->pending <= $explainedPending) {
                return;
            }

            $findings[] = new AuditFinding(
                severity: 'warning',
                code: 'STALE_PENDING_BALANCE',
                message: 'Customer still has pending balance (legacy pre-settlement or missing wallet credit).',
                context: [
                    'customer_id' => $balance->customer_id,
                    'currency' => strtoupper($balance->currency),
                    'pending' => (int) $balance->pending,
                    'wallester_allocated' => (int) $balance->wallester_allocated,
                    'explained_pending' => $explainedPending,
                ],
            );
        });

        return $findings;
    }

    /**
     * Pending that is expected: last snapshot's pending_after when present,
     * otherwise open PAYMENT_RECEIVED in pending bucket
     * (merchant awaiting Stripe payout, or top-up awaiting Wallester release).
     */
    private function explainedPendingCents(int $customerId, string $currency): int
    {
        $latestPendingAfter = LedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->whereNotNull('pending_after')
            ->orderByDesc('id')
            ->value('pending_after');

        if ($latestPendingAfter !== null) {
            return max(0, (int) $latestPendingAfter);
        }

        $pendingCredits = (int) LedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->where('direction', TransactionDirection::Credit)
            ->where('metadata->bucket', 'pending')
            ->sum('amount');

        $pendingDebits = (int) LedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->where('direction', TransactionDirection::Debit)
            ->where('metadata->bucket', 'pending')
            ->sum('amount');

        return max(0, $pendingCredits - $pendingDebits);
    }

    /** @return list<AuditFinding> */
    private function checkBalanceConsistency(?int $customerId, ?string $currency): array
    {
        $findings = [];

        $query = CustomerBalance::query();

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        $query->each(function (CustomerBalance $balance) use (&$findings): void {
            if (! $this->ledgerService->isBalanceConsistent($balance->customer_id, $balance->currency)) {
                $fromLedger = $this->ledgerService->recalculateBalance($balance->customer_id, $balance->currency);

                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'LEDGER_BALANCE_MISMATCH',
                    message: 'customer_balances.wallester_allocated does not match ledger sum.',
                    context: [
                        'customer_id' => $balance->customer_id,
                        'currency' => strtoupper($balance->currency),
                        'wallester_allocated' => (int) $balance->wallester_allocated,
                        'ledger_available' => $fromLedger,
                    ],
                );
            }
        });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkWiseAllocationMatchesLedger(?int $customerId, ?string $currency): array
    {
        $findings = [];

        $query = CustomerBalance::query();

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        $query->each(function (CustomerBalance $balance) use (&$findings): void {
            $wallesterAllocated = (int) $balance->wallester_allocated;
            $allocated = (int) $balance->wise_allocated;

            if ($wallesterAllocated !== $allocated) {
                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'WISE_ALLOCATION_LEDGER_MISMATCH',
                    message: 'customer_balances.wise_allocated does not match wallester_allocated.',
                    context: [
                        'customer_id' => $balance->customer_id,
                        'currency' => strtoupper((string) $balance->currency),
                        'wallester_allocated' => $wallesterAllocated,
                        'wise_allocated' => $allocated,
                    ],
                );
            }
        });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkWallesterMirrorVsWiseAllocation(?int $customerId, ?string $currency): array
    {
        $findings = [];

        $query = CustomerWallesterAccount::query()
            ->whereNotNull('wallester_account_id');

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($currency !== null) {
            $query->where(function (Builder $builder) use ($currency): void {
                $code = strtoupper($currency);
                $builder->where('funding_currency', $code)
                    ->orWhere('currency_code', $code);
            });
        }

        $query->each(function (CustomerWallesterAccount $account) use (&$findings): void {
            $currencyCode = strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));
            $wallesterCents = (int) ($account->available_amount ?? 0);

            $allocated = (int) (CustomerBalance::query()
                ->where('customer_id', $account->customer_id)
                ->where('currency', $currencyCode)
                ->value('wise_allocated') ?? 0);

            if ($wallesterCents !== $allocated) {
                $findings[] = new AuditFinding(
                    severity: 'warning',
                    code: 'WALLESTER_WISE_ALLOCATION_MISMATCH',
                    message: 'Wallester available_amount does not match wise_allocated for customer.',
                    context: [
                        'customer_id' => $account->customer_id,
                        'currency' => $currencyCode,
                        'wallester_available_cents' => $wallesterCents,
                        'wise_allocated' => $allocated,
                        'wallester_account_id' => $account->wallester_account_id,
                    ],
                );
            }
        });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkWiseMasterVsAllocations(?string $currency): array
    {
        $findings = [];

        try {
            $balances = $this->wiseGateway->listBalances();
        } catch (Throwable $exception) {
            $findings[] = new AuditFinding(
                severity: 'warning',
                code: 'WISE_MASTER_BALANCE_UNAVAILABLE',
                message: 'Could not fetch Wise master balances for allocation reconciliation.',
                context: [
                    'error' => $exception->getMessage(),
                ],
            );

            return $findings;
        }

        $currencies = $currency !== null
            ? [strtoupper($currency)]
            : collect($balances)
                ->pluck('currency')
                ->filter()
                ->map(fn ($code) => strtoupper((string) $code))
                ->unique()
                ->values()
                ->all();

        foreach ($currencies as $code) {
            $master = collect($balances)->first(
                fn (array $balance): bool => strtoupper((string) ($balance['currency'] ?? '')) === $code
                    && ($balance['type'] ?? 'STANDARD') === 'STANDARD'
            );

            if ($master === null) {
                continue;
            }

            $amount = $master['amount'] ?? 0;
            $masterCents = is_array($amount)
                ? (int) round(((float) ($amount['value'] ?? 0)) * 100)
                : (int) round(((float) $amount) * 100);
            $allocatedSum = (int) CustomerBalance::query()
                ->where('currency', $code)
                ->sum('wise_allocated');

            if ($masterCents < $allocatedSum) {
                $findings[] = new AuditFinding(
                    severity: 'critical',
                    code: 'WISE_MASTER_BELOW_ALLOCATIONS',
                    message: 'Wise master STANDARD balance is below the sum of customer wise_allocated.',
                    context: [
                        'currency' => $code,
                        'wise_master_cents' => $masterCents,
                        'wise_allocated_sum' => $allocatedSum,
                        'shortfall_cents' => $allocatedSum - $masterCents,
                    ],
                );
            }
        }

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkTopupWallesterMovements(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                $movement = $this->findTopupOutMovement($transaction);

                if ($movement === null) {
                    return;
                }

                if ((int) $movement->amount !== (int) $transaction->net_amount) {
                    $findings[] = new AuditFinding(
                        severity: 'critical',
                        code: 'TOPUP_WALLESTER_AMOUNT_MISMATCH',
                        message: 'Wallester top-up movement amount must equal transaction net_amount.',
                        context: [
                            'transaction_id' => $transaction->id,
                            'customer_id' => $transaction->customer_id,
                            'net_amount' => (int) $transaction->net_amount,
                            'movement_amount' => (int) $movement->amount,
                            'movement_id' => $movement->id,
                        ],
                    );
                }
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkMissingWallesterTopups(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->where('wallester_funding_net_amount', '>', 0)
            ->where('net_amount', '>', 0)
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                $movement = $this->findTopupOutMovement($transaction);

                if ($movement !== null) {
                    return;
                }

                $fundingStatus = $transaction->partner_funding_status;

                if ($fundingStatus === PartnerFundingStatus::AwaitingLiquidity
                    || $fundingStatus === PartnerFundingStatus::Failed) {
                    return;
                }

                $wallesterAccount = CustomerWallesterAccount::query()
                    ->where('customer_id', $transaction->customer_id)
                    ->where('currency_code', $transaction->currency)
                    ->first();

                if ($wallesterAccount === null || ! $wallesterAccount->status->isOperational()) {
                    return;
                }

                $findings[] = new AuditFinding(
                    severity: 'warning',
                    code: 'MISSING_WALLESTER_TOPUP',
                    message: 'Eligible top-up has no Wallester funding_partner_movement.',
                    context: [
                        'transaction_id' => $transaction->id,
                        'customer_id' => $transaction->customer_id,
                        'net_amount' => (int) $transaction->net_amount,
                        'wallester_account_id' => $wallesterAccount->wallester_account_id,
                    ],
                );
            });

        return $findings;
    }

    /** @return list<AuditFinding> */
    private function checkPartnerFundingStatuses(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): array {
        $findings = [];

        $this->creditTransactionQuery($from, $to, $customerId, $currency)
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->whereNotNull('partner_funding_status')
            ->each(function (StripeTransaction $transaction) use (&$findings): void {
                $status = $transaction->partner_funding_status;

                if (! $status instanceof PartnerFundingStatus) {
                    return;
                }

                if ($status === PartnerFundingStatus::AwaitingLiquidity) {
                    $findings[] = new AuditFinding(
                        severity: 'warning',
                        code: 'AWAITING_PLATFORM_LIQUIDITY',
                        message: 'Top-up is waiting for main Wallester account liquidity.',
                        context: [
                            'transaction_id' => $transaction->id,
                            'customer_id' => $transaction->customer_id,
                            'net_amount' => (int) $transaction->net_amount,
                            'failure_reason' => $transaction->partner_funding_failure_reason,
                        ],
                    );

                    return;
                }

                if ($status === PartnerFundingStatus::Failed) {
                    $movement = FundingPartnerMovement::query()
                        ->where('idempotency_key', 'stripe_topup:'.$transaction->id.':out')
                        ->first();

                    if ($movement?->status === FundingPartnerMovementStatus::Failed) {
                        $findings[] = new AuditFinding(
                            severity: 'critical',
                            code: 'FAILED_WALLESTER_TOPUP',
                            message: 'Wallester top-up funding failed after retries.',
                            context: [
                                'transaction_id' => $transaction->id,
                                'customer_id' => $transaction->customer_id,
                                'net_amount' => (int) $transaction->net_amount,
                                'movement_id' => $movement->id,
                                'failure_reason' => $transaction->partner_funding_failure_reason,
                            ],
                        );
                    }

                    return;
                }

                if ($status === PartnerFundingStatus::Pending
                    && $transaction->balance_settled_at !== null
                    && $transaction->balance_settled_at->lte(now()->subMinutes(self::PARTNER_FUNDING_STALE_MINUTES))) {
                    $findings[] = new AuditFinding(
                        severity: 'warning',
                        code: 'PENDING_PARTNER_FUNDING',
                        message: 'Top-up partner funding has been pending longer than expected.',
                        context: [
                            'transaction_id' => $transaction->id,
                            'customer_id' => $transaction->customer_id,
                            'net_amount' => (int) $transaction->net_amount,
                            'balance_settled_at' => $transaction->balance_settled_at?->toIso8601String(),
                        ],
                    );
                }
            });

        return $findings;
    }

    private function creditTransactionQuery(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): Builder {
        return $this->transactionQuery($from, $to, $customerId, $currency)
            ->where('direction', TransactionDirection::Credit->value)
            ->whereNull('related_transaction_id');
    }

    private function transactionQuery(
        ?string $from,
        ?string $to,
        ?int $customerId,
        ?string $currency,
    ): Builder {
        $query = StripeTransaction::query();

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        return $query;
    }

    /** @return Collection<int, array{customer_id: int, currency: string}> */
    private function customerCurrencyPairs(?int $customerId, ?string $currency): Collection
    {
        $fromBalances = CustomerBalance::query()
            ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->when($currency !== null, fn ($q) => $q->where('currency', strtoupper($currency)))
            ->get(['customer_id', 'currency']);

        $fromTransactions = StripeTransaction::query()
            ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->when($currency !== null, fn ($q) => $q->where('currency', strtoupper($currency)))
            ->whereNull('related_transaction_id')
            ->select('customer_id', 'currency')
            ->distinct()
            ->get();

        return collect()
            ->merge($fromBalances->map(fn (CustomerBalance $b) => [
                'customer_id' => $b->customer_id,
                'currency' => strtoupper($b->currency),
            ]))
            ->merge($fromTransactions->map(fn (StripeTransaction $t) => [
                'customer_id' => $t->customer_id,
                'currency' => strtoupper($t->currency),
            ]))
            ->unique(fn (array $pair) => $pair['customer_id'].'|'.$pair['currency'])
            ->values();
    }

    private function hasPaymentLedgerEntry(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->exists();
    }

    private function hasAvailableWalletCredit(StripeTransaction $transaction): bool
    {
        $hasAvailablePayment = LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where(function ($query): void {
                $query->whereNull('metadata->bucket')
                    ->orWhere('metadata->bucket', '!=', 'pending');
            })
            ->exists();

        if ($hasAvailablePayment) {
            return true;
        }

        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PARTNER_FUNDING_RELEASED)
            ->exists();
    }

    private function hasPendingWalletCredit(StripeTransaction $transaction): bool
    {
        if ($this->ledgerService->hasPendingCreditReversal($transaction)) {
            return false;
        }

        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where('metadata->bucket', 'pending')
            ->exists();
    }

    private function findTopupOutMovement(StripeTransaction $transaction): ?FundingPartnerMovement
    {
        return FundingPartnerMovement::query()
            ->where('idempotency_key', 'stripe_topup:'.$transaction->id.':out')
            ->whereIn('status', [
                FundingPartnerMovementStatus::Completed,
                FundingPartnerMovementStatus::Pending,
            ])
            ->first();
    }
}
