<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionLabelsSeeder extends Seeder
{
    /** @var array<string, string> */
    private const LABELS = [
        'customers.view' => 'Ver clientes',
        'customers.create' => 'Criar clientes',
        'customers.edit' => 'Editar clientes',
        'customers.delete' => 'Eliminar clientes',
        'customers.adjustments_balance' => 'Ajustar saldo',
        'customers.balance_adjustment_otp' => 'Receber SMS de confirmação de saldo',
        'users.view' => 'Ver utilizadores',
        'users.create' => 'Criar utilizadores',
        'users.edit' => 'Editar utilizadores',
        'users.delete' => 'Eliminar utilizadores',
        'roles.view' => 'Ver roles',
        'roles.create' => 'Criar roles',
        'roles.edit' => 'Editar roles',
        'roles.delete' => 'Eliminar roles',
        'transactions.view' => 'Ver transações',
        'reports.view' => 'Ver relatórios',
        'settings.view' => 'Ver configurações',
        'settings.edit' => 'Editar configurações',
        'stripe_fees.view' => 'Ver taxas Stripe',
        'stripe_fees.create' => 'Criar taxas Stripe',
        'stripe_fees.edit' => 'Editar taxas Stripe',
        'stripe_fees.delete' => 'Eliminar taxas Stripe',
        'accounts_payable.view' => 'Ver contas a pagar',
        'accounts_payable.create' => 'Criar contas a pagar',
        'accounts_payable.edit' => 'Editar contas a pagar',
        'accounts_payable.delete' => 'Eliminar contas a pagar',
        'accounts_payable.pay' => 'Pagar contas',
        'audit_logs.view' => 'Ver logs de auditoria',
        'news.view' => 'Ver notícias',
        'news.create' => 'Criar notícias',
        'news.edit' => 'Editar notícias',
        'news.delete' => 'Eliminar notícias',
        'rewards.view' => 'Ver recompensas',
        'rewards.create' => 'Criar programas de recompensa',
        'rewards.edit' => 'Editar programas de recompensa',
        'rewards.delete' => 'Eliminar programas de recompensa',
    ];

    public function run(): void
    {
        foreach (self::LABELS as $name => $label) {
            Permission::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->update(['label' => $label]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
