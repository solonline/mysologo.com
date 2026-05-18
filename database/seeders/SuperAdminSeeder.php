<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     *
     *php artisan db:seed --class=SuperAdminSeeder
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@yoop.com'],
            [
                'name'     => 'Super Admin',
                'username' => 'superadmin',
                'email'    => 'admin@yoop.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([Role::findByName('super-admin', 'web')]);
    }
}
