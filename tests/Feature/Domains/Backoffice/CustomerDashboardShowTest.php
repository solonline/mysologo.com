<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use App\Models\Merchant;
use App\Models\StripeTransaction;
use App\Models\User;
use App\Models\WiseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerDashboardShowTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Permission::findOrCreate('customers.edit', 'web');
    }

    public function test_customer_show_displays_didit_session_and_kyc_warnings(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = Customer::factory()->create([
            'name' => 'Mario Rossi',
            'status' => CustomerLifecycleStatus::IDENTITY_REJECTED,
            'status_reason' => 'Identidade recusada pelo provedor de verificação.',
            'doc_status' => 'declined',
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => '9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            'status' => 'Declined',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://verify.didit.me/en/session/test',
            'metadata' => [
                'application_id' => 'c159a625-9dde-444c-990d-9accf2298448',
                'session_id' => '9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            ],
        ]);

        CustomerRetailDocument::query()->create([
            'customer_id' => $customer->id,
            'document_number' => 'didit:9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            'document_type' => 'Identity Card',
            'issuing_state_name' => 'Italy',
            'extra_fields' => [
                'status' => 'Declined',
                'document_subtype' => 'ID_CARD_GENERIC',
                'warnings' => [
                    [
                        'log_type' => 'error',
                        'risk' => 'SCREEN_CAPTURE_DETECTED',
                        'short_description' => 'Screen capture of document detected',
                        'long_description' => 'Document appears to be a screen capture.',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertDontSee('Verificação Didit');
        $response->assertSee('Documentos KYC');
        $response->assertSee('Motivos da verificação');
        $response->assertSee('Screen capture of document detected');
        $response->assertSee('SCREEN_CAPTURE_DETECTED');
        $response->assertSee('ID_CARD_GENERIC');
        $response->assertSee('Identidade recusada pelo provedor de verificação.');
    }

    public function test_customer_show_displays_stripe_funding_iban_when_active(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_show_funding', [
            'funding_status' => StripeFundingStatus::ACTIVE,
            'funding_iban' => 'DE89370400440532013000',
            'funding_bic' => 'COBADEFFXXX',
            'funding_account_holder_name' => 'Mario Rossi',
            'bank_transfer_type' => 'eu_bank_transfer',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Conta Stripe');
        $response->assertSee('IBAN');
        $response->assertSee('DE89370400440532013000');
        $response->assertSee('COBADEFFXXX');
        $response->assertSee('Mario Rossi');
        $response->assertSee('Activo');
    }

    public function test_customer_show_displays_stripe_funding_failure_and_retry_button(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findByName('customers.view', 'web'),
            Permission::findByName('customers.edit', 'web'),
        ]);

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_show_failed', [
            'funding_status' => StripeFundingStatus::FAILED,
            'funding_status_reason' => 'Stripe API unavailable',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Conta Stripe');
        $response->assertSee('A criação do IBAN falhou.');
        $response->assertSee('Stripe API unavailable');
        $response->assertSee('Repetir criação do IBAN');
        $response->assertSee(route('dashboard.customers.retry-stripe-funding', $customer), false);
    }

    public function test_customer_show_displays_add_wallester_card_button_when_account_is_active(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findByName('customers.view', 'web'),
            Permission::findByName('customers.edit', 'web'),
        ]);

        $customer = $this->createRetailCustomer(['name' => 'Cliente Cartao']);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_card_btn');

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Conta Wallester');
        $response->assertSee('0 cartões');
        $response->assertSee('Adicionar cartão');
        $response->assertSee(route('dashboard.customers.wallester-cards.store', $customer), false);
        $response->assertSee('Nenhum cartão emitido.');
    }

    public function test_customer_show_hides_add_wallester_card_button_without_edit_permission(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_card_hidden');

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertDontSee('Adicionar cartão');
        $response->assertDontSee(route('dashboard.customers.wallester-cards.store', $customer), false);
        $response->assertDontSee('Transferir cartão');
    }

    public function test_customer_show_displays_link_card_form_for_open_card(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findByName('customers.view', 'web'),
            Permission::findByName('customers.edit', 'web'),
        ]);

        $customer = $this->createRetailCustomer(['name' => 'Cliente Com Cartao']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_link_card');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_show_link',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Cartao Ativo',
            'masked_card_number' => '****1111',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Selecionar cartão');
        $response->assertSee('Username do destinatário');
        $response->assertSee('Transferir cartão');
        $response->assertSee(route('dashboard.customers.wallester-cards.link', $customer), false);
        $response->assertSee('username-search');
        $response->assertSee('value="'.$card->id.'"', false);
    }

    public function test_customer_show_hides_link_card_form_for_closed_card(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findByName('customers.view', 'web'),
            Permission::findByName('customers.edit', 'web'),
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_closed_card');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_show_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Cartao Encerrado',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Encerrado');
        $response->assertDontSee('Transferir cartão');
        $response->assertDontSee('Selecionar cartão');
        $response->assertDontSee(route('dashboard.customers.wallester-cards.link', $customer), false);
    }

    public function test_customer_show_displays_unified_movements_with_card_purchase(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = $this->createRetailCustomer([
            'name' => 'Cliente Movimentos',
        ]);

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_movements');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Carregamento teste',
            'stripe_created_at' => now()->subDay(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 90,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:show-purchase:out',
            'partner_external_id' => 'show-purchase',
            'description' => 'DISTRICT',
            'metadata' => [
                'merchant_city' => 'SETUBAL',
                'merchant_category_code' => '7339',
            ],
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Movimentos');
        $response->assertSee('Entradas e saídas');
        $response->assertSee('Carregamento teste');
        $response->assertSee('DISTRICT');
        $response->assertSee('Compra cartão');
        $response->assertSee('Wallester');
        $response->assertSee('Stripe');
        $response->assertSee('SETUBAL');
        $response->assertSee('MCC 7339');
        $response->assertSee('+1 entradas');
        $response->assertSee('−1 saídas');
    }

    public function test_customer_show_displays_wallester_account_transfer_like_api(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente Transferência']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_transfer');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 7_500,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'show-transfer:in',
            'description' => 'Funding Wallester',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Funding Wallester')
            ->assertSee('Transferência')
            ->assertSee('75,00')
            ->assertSee('+1 entradas');
    }

    public function test_customer_show_does_not_duplicate_p2p_as_stripe_and_wallester(): void
    {
        $user = $this->userWithPermission('customers.view');
        $recipient = $this->createRetailCustomer([
            'name' => 'Destinatario Show P2P',
            'username' => 'dest_show_p2p',
        ]);
        $sender = $this->createRetailCustomer([
            'name' => 'Remetente Show P2P',
            'username' => 'rem_show_p2p',
        ]);
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_show_p2p_in');
        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_show_p2p_out');

        StripeTransaction::query()->create([
            'customer_id' => $recipient->id,
            'type' => TransactionType::P2P,
            'payment_method_type' => 'p2p',
            'direction' => 'credit',
            'amount' => 1,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 1,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'P2P transfer nao duplicar',
            'stripe_created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $recipientAccount::class,
            'funding_account_id' => $recipientAccount->id,
            'external_account_id' => $recipientAccount->wallester_account_id,
            'counterparty_external_account_id' => $senderAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 1,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:show-no-dup:in',
            'description' => 'P2P transfer nao duplicar',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $recipient));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'data-customer-movement'));
        $this->assertSame(1, substr_count($response->getContent(), 'P2P transfer nao duplicar'));
        $response->assertSee('@rem_show_p2p');
        $response->assertSee('Remetente Show P2P');
    }

    public function test_customer_show_displays_unified_fx_card_purchase(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente FX']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_fx');

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
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:show-fx:out',
            'partner_external_id' => 'show-fx',
            'description' => 'LidlGB',
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('LidlGB');
        $response->assertSee('4,54');
        $response->assertSee('3,88 GBP');
        $response->assertSee('4,54 EUR');
    }

    public function test_customer_show_lists_topup_once_with_available_amount_and_fee(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer(['name' => 'Cliente Topup']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_show_topup');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Bank transfer top-up via VIBAN',
            'wallester_funding_net_amount' => 298,
            'partner_funding_status' => 'completed',
            'partner_funded_at' => now(),
            'balance_settled_at' => now(),
            'available_on' => '2026-10-21',
            'stripe_created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 298,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':in',
            'description' => 'Stripe top-up #'.$transaction->id,
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'data-customer-movement'));
        $response->assertSee('3,00');
        $response->assertSee('disponível em 21/10/2026');
        $response->assertSee('taxas 0,02');
        $response->assertSee('Pendente');
        $response->assertDontSee('disponível 2,98');
        $response->assertDontSee('Stripe top-up #'.$transaction->id);
        $response->assertDontSee('Wallester imediato');
    }

    public function test_customer_show_limits_recent_movements_to_ten_and_links_to_full_list(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer();

        foreach (range(1, 12) as $number) {
            StripeTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => TransactionType::TOPUP,
                'payment_method_type' => 'card',
                'direction' => 'credit',
                'amount' => 1_000 + $number,
                'stripe_fee' => 0,
                'platform_fee' => 0,
                'net_amount' => 1_000 + $number,
                'currency' => 'EUR',
                'status' => 'succeeded',
                'description' => sprintf('Movimento resumo %02d', $number),
                'stripe_created_at' => now()->addMinutes($number),
            ]);
        }

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee(route('dashboard.customers.transactions', $customer), false);
        $response->assertSee('Movimento resumo 12');
        $this->assertSame(10, substr_count($response->getContent(), 'data-customer-movement'));
    }

    public function test_customer_show_does_not_offer_securepay(): void
    {
        $this->withoutVite();

        $user = $this->userWithPermission('customers.view');
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ]);

        Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Backoffice',
            'slug' => 'loja-backoffice-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_bo_'.uniqid()),
            'mode' => 'test',
            'webhook_url' => 'https://loja.exemplo.com/webhook',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertDontSee('SecurePay');
        $response->assertDontSee('Loja Backoffice');
        $response->assertDontSee('securepay.png', false);
    }

    public function test_platform_customer_show_uses_wallester_master_cash(): void
    {
        $user = $this->userWithPermission('customers.view');
        $platform = Customer::factory()->create([
            'type' => 'platform',
            'name' => 'Yoop Platform',
        ]);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-show', [
            'is_main' => true,
            'available_amount' => 522,
            'balance' => 522,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        $feeAccount = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, '626c80a3-f8e7-4627-8bcf-ca73f9944abf', [
            'is_main' => false,
            'receiver_name' => 'Sub Conta Master',
            'available_amount' => 15,
            'balance' => 15,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 1598,
            'pending' => 0,
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => CustomerWallesterAccount::class,
            'funding_account_id' => $feeAccount->id,
            'external_account_id' => $feeAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:master-show:in',
            'description' => 'Câmbio Yoop',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $platform))
            ->assertOk()
            ->assertSee('5,22')
            ->assertSee('Caixa Wallester')
            ->assertSee('Sub Conta Master')
            ->assertSee('Câmbio Yoop')
            ->assertSee('0,05')
            ->assertDontSee('15,98');
    }

    public function test_customer_show_displays_captive_wise_transfer(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = Customer::factory()->create([
            'name' => 'Cliente Cativo',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 125,
            'pending' => 767,
            'wise_allocated' => 125,
        ]);

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440842090,
            'wise_batch_group_id' => '64a6d684-3be0-4b40-b3e9-4e22f6f8e9f7',
            'wise_recipient_id' => 1612703775,
            'beneficiary_name' => 'TAMIETTO JUNIOR, UNIPESSOAL, LDA',
            'reference' => 'Tamietto 1 EUR',
            'currency' => 'EUR',
            'amount_cents' => 100,
            'fee_cents' => 0,
            'fee_currency' => 'EUR',
            'idempotency_key' => 'wise_transfer:dashboard-captive',
            'status' => 'incoming_payment_waiting',
        ]);

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440842091,
            'wise_batch_group_id' => '64a6d684-3be0-4b40-b3e9-4e22f6f8e9f8',
            'wise_recipient_id' => 1612703776,
            'beneficiary_name' => 'Ja enviado',
            'reference' => 'Enviado',
            'currency' => 'EUR',
            'amount_cents' => 500,
            'idempotency_key' => 'wise_transfer:dashboard-settled',
            'status' => 'outgoing_payment_sent',
            'settled_at' => now(),
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Cativo')
            ->assertSee('1,00')
            ->assertSee('TAMIETTO JUNIOR, UNIPESSOAL, LDA')
            ->assertSee('Tamietto 1 EUR')
            ->assertSee('9,92')
            ->assertDontSee('Ja enviado');
    }

    private function userWithPermission(string $permission): User
    {
        $permissionModel = Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permissionModel);

        return $user;
    }
}
