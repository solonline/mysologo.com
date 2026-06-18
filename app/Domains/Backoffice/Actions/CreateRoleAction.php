<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\RoleData;
use Spatie\Permission\Models\Role;

class CreateRoleAction
{
    public function execute(RoleData $data): Role
    {
        $role = Role::create(['name' => $data->name, 'guard_name' => 'web']);

        if (! empty($data->permissions)) {
            $role->syncPermissions($data->permissions);
        }

        return $role;
    }
}
