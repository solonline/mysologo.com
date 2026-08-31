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

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions'))
            ->assertOk()
            ->assertSee('Stripe')
            ->assertSee('Wallester')
            ->assertSee('Carregamento')
            ->assertSee('Venda SecurePay')
            ->assertSee('Cliente Teste Transacoes')
            ->assertSee('Ver detalhes')
            ->assertSee('Detalhes da transação');

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('Transferência')
            ->assertSee('wal-dashboard-test')
            ->assertSee('Ver detalhes');
    }

    public function test_wallester_tab_shows_unified_fx_purchase(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente Lidl']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal-fx-dashboard');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 463,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:dashboard-fx:out',
            'partner_external_id' => 'dashboard-fx',
            'description' => 'LidlGB',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('LidlGB')
            ->assertSee('4,63')
            ->assertSee('3,88 GBP')
            ->assertSee('câmbio 0,09 EUR')
            ->assertSee('Compra');
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

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.transactions', $customer))
            ->assertOk()
            ->assertSee('Transações de Cliente Selecionado')
            ->assertSee('(1)')
            ->assertDontSee('Outro Cliente');

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.transactions', ['customer' => $customer, 'tab' => 'wallester']))
            ->assertOk()
            ->assertSee('wal-selected-customer')
            ->assertDontSee('wal-other-customer');
    }

    public function test_transactions_page_can_filter_by_merchant_payment_type(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $customer = $this->createRetailCustomer(['name' => 'Merchant Filter Customer']);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 1_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Topup nao deve aparecer',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_500,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_500,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Venda merchant filtro',
            'partner_funding_status' => 'awaiting_stripe_payout',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', [
                'tab' => 'stripe',
                'type' => TransactionType::MERCHANT_PAYMENT->value,
            ]))
            ->assertOk()
            ->assertSee('Venda SecurePay')
            ->assertSee('Venda merchant filtro')
            ->assertSee('Aguarda payout Stripe')
            ->assertDontSee('Topup nao deve aparecer');
    }

    private function userWithPermission(string $permission): User
    {
        $permissionModel = Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permissionModel);

        return $user;
    }
}
