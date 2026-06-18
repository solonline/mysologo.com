<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;

class DeleteAccountPayableAction
{
    public function execute(AccountPayable $account): void
    {
        $account->delete();
    }
}
