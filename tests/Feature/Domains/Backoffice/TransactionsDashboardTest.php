<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class TransactionsDashboardTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_transactions_page_lists_stripe_and_wallester_tabs(): void
    {
        $user = $this->userWithPermission('transactions.view');

        $customer = $this->createRetailCustomer([
            'name' => 'Cliente Teste Transacoes',
            'email' => 'transacoes@example.com',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Top-up teste dashboard',
        ]);

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal-dashboard-test');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5_000,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'dashboard-test:in',
            'description' => 'Transferencia teste dashboard',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.transactions'))
            ->assertOk()
            ->assertSee('Stripe')
            ->assertSee('Wallester')
            ->assertSee('Carregamento')
            ->assertSee('Cliente Teste Transacoes')
            ->assertSee('Ver detalhes')
            ->assertSee('Detalhes da transação');

        $this->actingAs($user)
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('Transferência')
            ->assertSee('wal-dashboard-test')
            ->assertSee('Ver detalhes');
    }

    public function test_guest_cannot_access_transactions_page(): void
    {
        $this->get(route('dashboard.transactions'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_transactions_page_only_lists_selected_customer_movements(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente Selecionado']);
        $otherCustomer = $this->createRetailCustomer(['name' => 'Outro Cliente']);

        foreach ([
            [$customer, 'Transação cliente selecionado'],
            [$otherCustomer, 'Transação outro cliente'],
        ] as [$transactionCustomer, $description]) {
            StripeTransaction::query()->create([
                'customer_id' => $transactionCustomer->id,
                'type' => TransactionType::TOPUP,
                'payment_method_type' => 'card',
                'direction' => 'credit',
                'amount' => 5_000,
                'stripe_fee' => 0,
                'platform_fee' => 0,
                'net_amount' => 5_000,
                'currency' => 'EUR',
                'status' => 'succeeded',
                'description' => $description,
            ]);
        }

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal-selected-customer');
        $otherAccount = $this->createWallesterAccount($otherCustomer, IntegrationAccountStatus::ACTIVE, 'wal-other-customer');

        foreach ([
            [$account, 'Movimento cliente selecionado'],
            [$otherAccount, 'Movimento outro cliente'],
        ] as [$movementAccount, $description]) {
            FundingPartnerMovement::query()->create([
                'provider' => 'wallester',
                'funding_account_type' => $movementAccount::class,
                'funding_account_id' => $movementAccount->id,
                'external_account_id' => $movementAccount->wallester_account_id,
                'direction' => FundingPartnerMovementDirection::In,
                'type' => FundingPartnerMovementType::AccountTransfer,
                'amount' => 5_000,
                'currency' => 'EUR',
                'status' => FundingPartnerMovementStatus::Completed,
                'source' => FundingPartnerMovementSource::Api,
                'idempotency_key' => 'customer-page:'.$movementAccount->id,
                'description' => $description,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard.customers.transactions', $customer))
            ->assertOk()
            ->assertSee('Transações de Cliente Selecionado')
            ->assertSee('(1)')
            ->assertDontSee('Outro Cliente');

        $this->actingAs($user)
            ->get(route('dashboard.customers.transactions', ['customer' => $customer, 'tab' => 'wallester']))
            ->assertOk()
            ->assertSee('wal-selected-customer')
            ->assertDontSee('wal-other-customer');
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}
