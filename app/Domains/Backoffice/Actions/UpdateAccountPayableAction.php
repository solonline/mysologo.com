<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;

class UpdateAccountPayableAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(AccountPayable $account, array $data): AccountPayable
    {
        $account->update($data);

        return $account->fresh();
    }
}
