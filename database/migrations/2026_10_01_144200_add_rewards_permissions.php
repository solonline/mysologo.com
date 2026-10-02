<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'rewards.view',
        'rewards.create',
        'rewards.edit',
        'rewards.delete',
    ];

    public function up(): void
    {
        $labels = [
            'rewards.view' => 'Ver recompensas',
            'rewards.create' => 'Criar programas de recompensa',
            'rewards.edit' => 'Editar programas de recompensa',
            'rewards.delete' => 'Eliminar programas de recompensa',
        ];

        foreach ($this->permissions as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $labels[$name]],
            );
        }

        $this->give('super-admin', $this->permissions);
        $this->give('admin', $this->permissions);
        $this->give('manager', ['rewards.view', 'rewards.create', 'rewards.edit']);
        $this->give('support', ['rewards.view']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function give(string $roleName, array $permissions): void
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->first();

        $role?->givePermissionTo($permissions);
    }
};
