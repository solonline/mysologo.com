<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Sms\Support\SmsLogger;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerBalanceAdjustmentApproval;
use App\Models\CustomerWallesterAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestCustomerBalanceAdjustmentAction
{
    public function __construct(
        protected SmsGatewayInterface $smsGateway,
    ) {}

    public function execute(
        Customer $customer,
        User $actor,
        CustomerBalanceAdjustmentDirection $direction,
        int $amountCents,
        string $description,
    ): CustomerBalanceAdjustment {
        $this->assertSuperAdmin($actor);
        $this->assertActorPhone($actor);

        if ($amountCents < 1) {
            throw ValidationException::withMessages([
                'amount' => 'O valor deve ser maior que zero.',
            ]);
        }

        $account = $this->requireActiveWallesterAccount($customer);
        $currency = strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));

        if ($direction === CustomerBalanceAdjustmentDirection::Debit) {
            $this->assertSufficientLedgerBalance($customer, $currency, $amountCents);
        }

        $adjustment = DB::transaction(function () use ($customer, $actor, $direction, $amountCents, $currency, $description) {
            $this->cancelPendingAdjustments($customer);

            $adjustment = CustomerBalanceAdjustment::query()->create([
                'customer_id' => $customer->id,
                'direction' => $direction,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'description' => $description,
                'status' => CustomerBalanceAdjustmentStatus::PendingApproval,
                'required_approvals' => 1,
                'requested_by' => $actor->id,
                'idempotency_key' => 'manual_adj:'.(string) Str::uuid(),
            ]);

            $this->createApproval($adjustment, $actor);

            return $adjustment->fresh(['approvals']);
        });

        $approval = $adjustment->approvals->firstWhere('user_id', $actor->id);
        if (! $approval instanceof CustomerBalanceAdjustmentApproval) {
            throw ValidationException::withMessages([
                'adjustment' => 'Falha ao criar aprovação SMS.',
            ]);
        }

        $this->sendOtpSms($actor, $approval, $adjustment);

        return $adjustment->fresh(['approvals']);
    }

    private function assertSuperAdmin(User $actor): void
    {
        if (! $actor->hasRole('super-admin', 'web')) {
            throw ValidationException::withMessages([
                'actor' => 'Apenas super-admin pode solicitar crédito ou débito.',
            ]);
        }
    }

    private function assertActorPhone(User $actor): void
    {
        $phone = preg_replace('/\D+/', '', (string) $actor->phone) ?: '';

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'O seu utilizador precisa de um telefone configurado para receber o SMS de autenticação.',
            ]);
        }
    }

    private function requireActiveWallesterAccount(Customer $customer): CustomerWallesterAccount
    {
        $account = $customer->wallesterAccount;

        if (! $account instanceof CustomerWallesterAccount) {
            throw ValidationException::withMessages([
                'wallester' => 'O cliente não tem conta Wallester.',
            ]);
        }

        $status = $account->status instanceof IntegrationAccountStatus
            ? $account->status
            : IntegrationAccountStatus::tryFrom((string) $account->status);

        if ($status !== IntegrationAccountStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'wallester' => 'A conta Wallester do cliente precisa de estar ACTIVE.',
            ]);
        }

        return $account;
    }

    private function assertSufficientLedgerBalance(Customer $customer, string $currency, int $amountCents): void
    {
        $available = (int) (CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', $currency)
            ->value('available') ?? 0);

        if ($available < $amountCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo ledger insuficiente. Disponível: {$available} {$currency}.",
            );
        }
    }

    private function cancelPendingAdjustments(Customer $customer): void
    {
        CustomerBalanceAdjustment::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerBalanceAdjustmentStatus::PendingApproval)
            ->update(['status' => CustomerBalanceAdjustmentStatus::Cancelled]);
    }

    private function createApproval(
        CustomerBalanceAdjustment $adjustment,
        User $actor,
    ): CustomerBalanceAdjustmentApproval {
        $code = (string) random_int(100000, 999999);

        return CustomerBalanceAdjustmentApproval::query()->create([
            'adjustment_id' => $adjustment->id,
            'user_id' => $actor->id,
            'code' => $code,
            'code_expires_at' => now()->addMinutes(2),
            'sms_sent_at' => null,
            'verified_at' => null,
        ]);
    }

    private function sendOtpSms(
        User $actor,
        CustomerBalanceAdjustmentApproval $approval,
        CustomerBalanceAdjustment $adjustment,
    ): void {
        $phone = preg_replace('/\D+/', '', (string) $actor->phone) ?: (string) $actor->phone;
        $amount = number_format($adjustment->amount_cents / 100, 2, ',', '.');
        $directionLabel = $adjustment->direction->label();

        $message = "Yoop: código {$approval->code} para {$directionLabel} de {$amount} {$adjustment->currency} "
            ."no cliente #{$adjustment->customer_id}. Válido 2 min.";

        SmsLogger::info('balance_adjustment_sms_dispatch', [
            'adjustment_id' => $adjustment->id,
            'user_id' => $actor->id,
            'phone' => $phone,
            'code' => $approval->code,
        ]);

        $result = $this->smsGateway->send($phone, $message);

        SmsLogger::info('balance_adjustment_sms_result', [
            'adjustment_id' => $adjustment->id,
            'user_id' => $actor->id,
            'phone' => $phone,
            'provider_status' => $result['status'] ?? 'unknown',
            'http_code' => $result['http_code'] ?? null,
        ]);

        if (($result['status'] ?? 'failed') !== 'success') {
            $adjustment->update([
                'status' => CustomerBalanceAdjustmentStatus::Failed,
                'failure_reason' => 'Falha ao enviar SMS de autenticação.',
            ]);

            throw ValidationException::withMessages([
                'sms' => 'Não foi possível enviar o SMS de autenticação. Tente novamente.',
            ]);
        }

        $approval->update(['sms_sent_at' => now()]);
    }
}
