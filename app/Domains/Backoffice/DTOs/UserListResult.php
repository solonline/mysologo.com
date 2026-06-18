<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class UserListResult
{
    /**
     * @param  Collection<int, \Spatie\Permission\Models\Role>  $roles
     */
    public function __construct(
        public LengthAwarePaginator $users,
        public Collection $roles,
    ) {}
}
