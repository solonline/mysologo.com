<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\UserListResult;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class ListUsersQuery
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function execute(array $filters): UserListResult
    {
        $query = User::query()->with('roles');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $filters['role'] ?? null) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        return new UserListResult(
            users: $query->latest()->paginate(15),
            roles: Role::orderBy('name')->get(),
        );
    }

    public function allRoles()
    {
        return Role::orderBy('name')->get();
    }

    public function findWithRoles(User $user): User
    {
        return $user->load('roles');
    }
}
