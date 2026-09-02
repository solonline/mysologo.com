<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
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
            'amount' => 454,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:dashboard-fx:out',
            'partner_external_id' => 'dashboard-fx',
            'description' => 'LidlGB',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::Fee,
            'amount' => 14,
            'currency' => 'EUR',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'fx-fees:dashboard-fx',
            'partner_external_id' => 'fx-fees:dashboard-fx',
            'description' => 'Câmbio',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:dashboard-fx:out',
            'description' => 'Platform FX fee dashboard-fx',
        ]);

        $platform = Customer::factory()->create([
            'type' => 'platform',
            'name' => 'Yoop Platform',
        ]);
        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-fx-dashboard', [
            'is_main' => true,
        ]);

        $purchase = FundingPartnerMovement::query()
            ->where('idempotency_key', 'card:dashboard-fx:out')
            ->firstOrFail();

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $master::class,
            'funding_account_id' => $master->id,
            'external_account_id' => $master->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:dashboard-fx:in',
            'description' => 'Platform FX fee dashboard-fx',
            'reference_type' => $purchase->getMorphClass(),
            'reference_id' => $purchase->id,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('LidlGB')
            ->assertSee('4,54')
            ->assertSee('3,88 GBP')
            ->assertSee('0,09 EUR')
            ->assertSee('0,05 EUR')
            ->assertSee('0,14')
            ->assertSee('Câmbio')
            ->assertSee('Câmbio Yoop')
            ->assertSee('Compra')
            ->assertSee('Yoop Platform');
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

    public function test_stripe_tab_excludes_p2p_which_appears_on_wallester_tab(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente P2P Dashboard']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal-p2p-dashboard');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::P2P,
            'payment_method_type' => 'p2p',
            'direction' => 'debit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'P2P transfer dashboard nao deve aparecer na stripe',
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
            'description' => 'Top-up deve aparecer na stripe',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2_000,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:dashboard-test:out',
            'description' => 'P2P transfer movimento wallester',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'stripe']))
            ->assertOk()
            ->assertSee('Top-up deve aparecer na stripe')
            ->assertDontSee('P2P transfer dashboard nao deve aparecer na stripe')
            ->assertDontSee('>P2P</option>', false);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('P2P transfer movimento wallester')
            ->assertSee('Transferência');
    }

    public function test_wallester_details_modal_shows_counterparty_username(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $sender = $this->createRetailCustomer([
            'name' => 'Remetente P2P',
            'username' => 'remetente_p2p',
        ]);
        $recipient = $this->createRetailCustomer([
            'name' => 'Destinatario P2P',
            'username' => 'destinatario_p2p',
        ]);
        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal-p2p-sender');
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal-p2p-recipient');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $senderAccount::class,
            'funding_account_id' => $senderAccount->id,
            'external_account_id' => $senderAccount->wallester_account_id,
            'counterparty_external_account_id' => $recipientAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2_000,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:counterparty-username:out',
            'description' => 'P2P com contraparte',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'wallester']))
            ->assertOk()
            ->assertSee('P2P com contraparte')
            ->assertSee('@destinatario_p2p')
            ->assertSee('Destinatario P2P')
            ->assertSee('wal-p2p-recipient');
    }

    private function userWithPermission(string $permission): User
    {
        $permissionModel = Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permissionModel);

        return $user;
    }
}
