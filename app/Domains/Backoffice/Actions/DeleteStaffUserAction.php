<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\User;

class DeleteStaffUserAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
