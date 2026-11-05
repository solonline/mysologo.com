<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerBalanceAdjustmentApproval;
use App\Models\FundingPartnerMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConfirmCustomerBalanceAdjustmentAction
{
    public function __construct(
        protected FundingPartnerMovementService $fundingPartnerMovements,
        protected LedgerService $ledger,
        protected RailMirrorService $railMirror,
    ) {}

    public function execute(
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
        User $actor,
        string $code,
    ): CustomerBalanceAdjustment {
        if ($adjustment->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'adjustment' => 'Ajuste inválido para este cliente.',
            ]);
        }

        if (! $actor->hasRole('super-admin', 'web')) {
            throw ValidationException::withMessages([
                'actor' => 'Apenas super-admin pode confirmar crédito ou débito.',
            ]);
        }

        if (! $adjustment->isPending()) {
            throw ValidationException::withMessages([
                'adjustment' => 'Não existe um ajuste pendente de aprovação.',
            ]);
        }

        $approval = CustomerBalanceAdjustmentApproval::query()
            ->where('adjustment_id', $adjustment->id)
            ->latest('id')
            ->first();

        if (! $approval instanceof CustomerBalanceAdjustmentApproval) {
            throw ValidationException::withMessages([
                'code' => 'Não existe aprovação SMS pendente para este ajuste.',
            ]);
        }

        if ($approval->isVerified()) {
            throw ValidationException::withMessages([
                'code' => 'Este código já foi confirmado.',
            ]);
        }

        if ($approval->isExpired()) {
            $adjustment->update(['status' => CustomerBalanceAdjustmentStatus::Expired]);

            throw ValidationException::withMessages([
                'code' => 'O código SMS expirou. Solicite um novo.',
            ]);
        }

        if ($approval->code !== $code) {
            throw ValidationException::withMessages([
                'code' => 'Código SMS inválido.',
            ]);
        }

        $approval->update(['verified_at' => now()]);

        $adjustment = $adjustment->fresh(['approvals']);

        if (! $adjustment->hasEnoughApprovals()) {
            return $adjustment;
        }

        return $this->executeTransferAndLedger($customer, $adjustment, $actor);
    }

    private function executeTransferAndLedger(
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
        User $actor,
    ): CustomerBalanceAdjustment {
        if ($adjustment->direction === CustomerBalanceAdjustmentDirection::Debit) {
            $available = (int) (CustomerBalance::query()
                ->where('customer_id', $customer->id)
                ->where('currency', $adjustment->currency)
                ->value('wallester_allocated') ?? 0);

            if ($available < $adjustment->amount_cents) {
                $adjustment->update([
                    'status' => CustomerBalanceAdjustmentStatus::Failed,
                    'failure_reason' => 'Saldo ledger insuficiente no momento da execução.',
                ]);

                throw new ApiException(
                    ErrorCode::INSUFFICIENT_BALANCE,
                    422,
                    'Saldo ledger insuficiente para debitar o cliente.',
                );
            }
        }

        try {
            if ($adjustment->direction === CustomerBalanceAdjustmentDirection::Credit) {
                $this->fundingPartnerMovements->transferFromMasterToCustomer(
                    to: $customer,
                    amountCents: $adjustment->amount_cents,
                    currency: $adjustment->currency,
                    description: $adjustment->description,
                    idempotencyKey: $adjustment->idempotency_key,
                    reference: $adjustment,
                );
            } else {
                $this->fundingPartnerMovements->transferFromCustomerToMaster(
                    from: $customer,
                    amountCents: $adjustment->amount_cents,
                    currency: $adjustment->currency,
                    description: $adjustment->description,
                    idempotencyKey: $adjustment->idempotency_key,
                    reference: $adjustment,
                );
            }
        } catch (Throwable $exception) {
            $adjustment->update([
                'status' => CustomerBalanceAdjustmentStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $partnerExternalId = FundingPartnerMovement::query()
            ->where('idempotency_key', $adjustment->idempotency_key.':out')
            ->value('partner_external_id');

        DB::transaction(function () use ($customer, $adjustment, $actor, $partnerExternalId): void {
            $entryType = $adjustment->direction === CustomerBalanceAdjustmentDirection::Credit
                ? LedgerEntryType::MANUAL_CREDIT
                : LedgerEntryType::MANUAL_DEBIT;

            $this->ledger->record(
                customerId: $customer->id,
                currency: $adjustment->currency,
                entryType: $entryType,
                amount: $adjustment->amount_cents,
                reference: $adjustment,
                description: $adjustment->description,
                metadata: [
                    'requested_by' => $actor->id,
                    'direction' => $adjustment->direction->value,
                    'wallester_payment_id' => $partnerExternalId,
                ],
            );

            $this->mirrorWiseAllocation($customer, $adjustment);

            $adjustment->update([
                'status' => CustomerBalanceAdjustmentStatus::Executed,
                'partner_external_id' => is_string($partnerExternalId) ? $partnerExternalId : null,
                'failure_reason' => null,
            ]);
        });

        return $adjustment->fresh(['approvals']);
    }

    private function mirrorWiseAllocation(Customer $customer, CustomerBalanceAdjustment $adjustment): void
    {
        $amountCents = (int) $adjustment->amount_cents;

        if ($amountCents <= 0) {
            return;
        }

        $idempotencyKey = $adjustment->idempotency_key.':wise';

        if ($adjustment->direction === CustomerBalanceAdjustmentDirection::Credit) {
            $this->railMirror->creditAllocation(
                customerId: (int) $customer->id,
                currency: $adjustment->currency,
                amountCents: $amountCents,
                idempotencyKey: $idempotencyKey,
                reason: 'manual_credit',
                reference: $adjustment,
            );

            return;
        }

        $this->railMirror->debitAllocation(
            customerId: (int) $customer->id,
            currency: $adjustment->currency,
            amountCents: $amountCents,
            idempotencyKey: $idempotencyKey,
            reason: 'manual_debit',
            reference: $adjustment,
            allowNegative: true,
        );
    }
}
