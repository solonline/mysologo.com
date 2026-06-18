<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\RoleData;
use Spatie\Permission\Models\Role;

class UpdateRoleAction
{
    public function execute(Role $role, RoleData $data): Role
    {
        $role->update(['name' => $data->name]);
        $role->syncPermissions($data->permissions ?? []);

        return $role->fresh(['permissions']);
    }
}
