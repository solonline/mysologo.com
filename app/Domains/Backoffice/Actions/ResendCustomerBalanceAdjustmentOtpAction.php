<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Sms\Support\SmsLogger;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerBalanceAdjustmentApproval;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResendCustomerBalanceAdjustmentOtpAction
{
    public function __construct(
        protected SmsGatewayInterface $smsGateway,
    ) {}

    public function execute(
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
        User $actor,
    ): CustomerBalanceAdjustment {
        if ($adjustment->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'adjustment' => 'Ajuste inválido para este cliente.',
            ]);
        }

        if (! $actor->hasRole('super-admin', 'web')) {
            throw ValidationException::withMessages([
                'actor' => 'Apenas super-admin pode reenviar o SMS.',
            ]);
        }

        if (! $adjustment->isPending()) {
            throw ValidationException::withMessages([
                'adjustment' => 'Não existe um ajuste pendente de aprovação.',
            ]);
        }

        $phone = preg_replace('/\D+/', '', (string) $actor->phone) ?: '';
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'O seu utilizador precisa de um telefone configurado.',
            ]);
        }

        $approval = CustomerBalanceAdjustmentApproval::query()
            ->where('adjustment_id', $adjustment->id)
            ->where('user_id', $actor->id)
            ->first();

        if (! $approval instanceof CustomerBalanceAdjustmentApproval) {
            throw ValidationException::withMessages([
                'adjustment' => 'Não existe aprovação SMS para o seu utilizador.',
            ]);
        }

        if ($approval->isVerified()) {
            throw ValidationException::withMessages([
                'adjustment' => 'Esta aprovação já foi confirmada.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $approval->update([
            'code' => $code,
            'code_expires_at' => now()->addMinutes(2),
            'sms_sent_at' => null,
        ]);

        $amount = number_format($adjustment->amount_cents / 100, 2, ',', '.');
        $directionLabel = $adjustment->direction->label();
        $message = "Yoop: código {$code} para {$directionLabel} de {$amount} {$adjustment->currency} "
            ."no cliente #{$adjustment->customer_id}. Válido 2 min.";

        SmsLogger::info('balance_adjustment_sms_resend', [
            'adjustment_id' => $adjustment->id,
            'user_id' => $actor->id,
            'phone' => $phone,
            'code' => $code,
        ]);

        $result = $this->smsGateway->send($phone, $message);

        if (($result['status'] ?? 'failed') !== 'success') {
            throw ValidationException::withMessages([
                'sms' => 'Não foi possível reenviar o SMS. Tente novamente.',
            ]);
        }

        $approval->update(['sms_sent_at' => now()]);

        return $adjustment->fresh(['approvals']);
    }
}
