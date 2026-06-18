<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\StaffUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateStaffUserAction
{
    public function execute(User $user, StaffUserData $data): User
    {
        $user->update([
            'name' => $data->name,
            'username' => $data->username,
            'email' => $data->email,
            'phone' => $data->phone,
        ]);

        if (! empty($data->password)) {
            $user->update(['password' => Hash::make($data->password)]);
        }

        $user->syncRoles(! empty($data->role) ? [$data->role] : []);

        return $user->fresh(['roles']);
    }
}
