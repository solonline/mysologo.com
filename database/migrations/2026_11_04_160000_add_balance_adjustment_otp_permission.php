<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'customers.balance_adjustment_otp';

    public function up(): void
    {
        Permission::query()->updateOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['label' => 'Receber SMS de confirmação de saldo'],
        );

        $role = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->first();

        $role?->givePermissionTo(self::PERMISSION);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', self::PERMISSION)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
