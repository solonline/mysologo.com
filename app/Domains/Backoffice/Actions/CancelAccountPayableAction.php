<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;

class CancelAccountPayableAction
{
    public function execute(AccountPayable $account): AccountPayable
    {
        $account->update([
            'status' => AccountPayable::STATUS_CANCELLED,
            'paid_at' => null,
        ]);

        return $account->fresh();
    }
}
