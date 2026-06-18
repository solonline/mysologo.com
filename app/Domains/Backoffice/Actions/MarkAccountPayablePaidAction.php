<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;

class MarkAccountPayablePaidAction
{
    public function execute(AccountPayable $account, ?string $paidAt = null): AccountPayable
    {
        $account->update([
            'status' => AccountPayable::STATUS_PAID,
            'paid_at' => $paidAt
                ? \Carbon\Carbon::parse($paidAt)->toDateString()
                : now()->toDateString(),
        ]);

        return $account->fresh();
    }
}
