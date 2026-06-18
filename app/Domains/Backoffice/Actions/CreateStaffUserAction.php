<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\StaffUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateStaffUserAction
{
    public function execute(StaffUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'username' => $data->username,
            'email' => $data->email,
            'phone' => $data->phone,
            'password' => Hash::make((string) $data->password),
        ]);

        if (! empty($data->role)) {
            $user->assignRole($data->role);
        }

        return $user;
    }
}
