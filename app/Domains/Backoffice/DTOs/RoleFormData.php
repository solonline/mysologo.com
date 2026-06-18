<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class RoleFormData
{
    /**
     * @param  Collection<string, \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Permission>>  $permissions
     * @param  list<string>  $selectedPermissions
     */
    public function __construct(
        public Collection $permissions,
        public array $selectedPermissions = [],
    ) {}
}

readonly class RoleDetailResult
{
    public function __construct(
        public \Spatie\Permission\Models\Role $role,
        public LengthAwarePaginator $users,
    ) {}
}
