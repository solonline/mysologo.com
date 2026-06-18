<?php

namespace App\Domains\Backoffice\Actions;

use Spatie\Permission\Models\Role;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        $role->delete();
    }
}
