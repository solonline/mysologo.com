<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;
use App\Models\AccountPayableAttachment;

class DeleteAccountPayableAttachmentAction
{
    public function execute(AccountPayable $account, AccountPayableAttachment $attachment): void
    {
        abort_unless($attachment->account_payable_id === $account->id, 404);

        $attachment->delete();
    }
}
