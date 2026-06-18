<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\RoleDetailResult;
use App\Domains\Backoffice\DTOs\RoleFormData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ListRolesQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions', 'users']);

        if ($search = $filters['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name')->paginate(20);
    }

    public function formData(?Role $role = null): RoleFormData
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        $selected = $role
            ? $role->permissions->pluck('name')->all()
            : [];

        return new RoleFormData($permissions, $selected);
    }

    public function detail(Role $role): RoleDetailResult
    {
        $role->load('permissions');

        return new RoleDetailResult(
            role: $role,
            users: $role->users()->latest()->paginate(10),
        );
    }
}
