<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // comando para executar o seeder: php artisan db:seed --class=RolesSeeder
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissões por módulo
        $permissions = [
            // Clientes
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'customers.adjustments_balance',
            // Utilizadores
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            // Transações
            'transactions.view',
            // Relatórios
            'reports.view',
            // Configurações
            'settings.view',
            'settings.edit',
            // Taxas Stripe
            'stripe_fees.view',
            'stripe_fees.create',
            'stripe_fees.edit',
            'stripe_fees.delete',
            // Contas a pagar
            'accounts_payable.view',
            'accounts_payable.create',
            'accounts_payable.edit',
            'accounts_payable.delete',
            'accounts_payable.pay',
            // Auditoria
            'audit_logs.view',
            // Notícias
            'news.view',
            'news.create',
            'news.edit',
            'news.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager',     'guard_name' => 'web']);
        $support = Role::firstOrCreate(['name' => 'support',     'guard_name' => 'web']);

        // super-admin tem tudo
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // admin — tudo exceto gestão de roles
        $admin->syncPermissions(
            Permission::where('guard_name', 'web')
                ->where('name', 'not like', 'roles.%')
                ->get()
        );

        // manager — clientes + transações + relatórios
        $manager->syncPermissions([
            'customers.view', 'customers.create', 'customers.edit',
            'transactions.view',
            'reports.view',
            'accounts_payable.view', 'accounts_payable.create', 'accounts_payable.edit', 'accounts_payable.pay',
            'news.view', 'news.create', 'news.edit', 'news.delete',
        ]);

        // support — só leitura
        $support->syncPermissions([
            'customers.view',
            'transactions.view',
            'news.view',
        ]);
    }
}
