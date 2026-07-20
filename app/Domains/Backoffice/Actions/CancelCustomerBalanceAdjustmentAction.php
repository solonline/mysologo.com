<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelCustomerBalanceAdjustmentAction
{
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
                'actor' => 'Apenas super-admin pode cancelar o ajuste.',
            ]);
        }

        if (! $adjustment->isPending()) {
            throw ValidationException::withMessages([
                'adjustment' => 'Não existe um ajuste pendente para cancelar.',
            ]);
        }

        $adjustment->update([
            'status' => CustomerBalanceAdjustmentStatus::Cancelled,
        ]);

        return $adjustment->fresh();
    }
}
