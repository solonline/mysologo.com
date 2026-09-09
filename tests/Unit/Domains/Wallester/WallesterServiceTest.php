<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\Wallester3dsService;
use App\Domains\Wallester\Services\WallesterCardRsaKeyService;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterSensitiveDataDecryptor;
use App\Domains\Wallester\Services\WallesterService;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_card_persists_response_and_increments_cards_count(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, externalId: 'wal_acc_123');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['account_id'] === 'wal_acc_123'), null)
            ->andReturn([
                'id' => 'card_abc',
                'account_id' => 'wal_acc_123',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'masked_card_number' => '****1234',
                'expiry_date' => '2029-08-24T14:15:22Z',
                'is_disposable' => false,
                'security' => [
                    'contactless_enabled' => true,
                    'withdrawal_enabled' => false,
                ],
                'limits' => [
                    'daily_purchase' => 100000,
                    'daily_purchase_used' => 0,
                ],
                '3d_secure_settings' => [
                    'email' => 'user@example.com',
                    'password' => 'secret',
                ],
                'card_notification_settings' => [
                    'instant_spend_update_enabled' => true,
                ],
                'created_at' => '2026-07-01T10:00:00Z',
                'updated_at' => '2026-07-01T10:00:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCard([
            'account_id' => 'wal_acc_123',
            'type' => 'Virtual',
            'name' => 'Travel Card',
        ]);

        $this->assertInstanceOf(CustomerWallesterCard::class, $card);
        $this->assertSame('card_abc', $card->wallester_card_id);
        $this->assertSame('Virtual', $card->type);
        $this->assertSame('Active', $card->status);
        $this->assertSame('Travel Card', $card->name);
        $this->assertSame('****1234', $card->masked_card_number);
        $this->assertSame(['contactless_enabled' => true, 'withdrawal_enabled' => false], $card->security);
        $this->assertSame(['email' => 'user@example.com'], $card->three_d_secure_settings);
        $this->assertSame(['instant_spend_update_enabled' => true], $card->notification_settings);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_abc',
            'status' => 'Active',
        ]);
        $this->assertSame(1, $account->fresh()->cards_count);
    }

    public function test_create_card_for_customer_injects_oob_settings_and_activates_virtual_card(): void
    {
        $customer = $this->createRetailCustomer(['phone' => '351 912 345 678']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_acc_oob');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account, $customer): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'Virtual'
                    && $payload['name'] === 'Main Card'
                    && $payload['3d_secure_settings'] === [
                        'email' => (string) $customer->email,
                        'mobile' => '+351912345678',
                        'out_of_band_enabled' => true,
                        'out_of_band_id' => (string) $account->wallester_oob_id,
                    ]
                    && $payload['security']['internet_purchase_enabled'] === true
                    && $payload['security']['contactless_enabled'] === true
                    && $payload['security']['withdrawal_enabled'] === false;
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_oob',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Main Card',
            ]);

        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_oob', (string) $customer->id)
            ->andReturn([
                'id' => 'card_oob',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Main Card',
        ]);

        $this->assertSame('card_oob', $card->wallester_card_id);
        $this->assertSame('Active', $card->status);
        $this->assertNotNull($card->activated_at);
    }

    public function test_create_card_for_customer_does_not_activate_physical_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'ChipAndPin'
                    && $payload['name'] === 'Physical Card'
                    && is_array($payload['delivery_address'] ?? null)
                    && $payload['delivery_address']['country_code'] === 'PRT'
                    && $payload['delivery_address']['dispatch_method'] === 'DHLGlobalMail'
                    && $payload['delivery_address']['company_name'] === 'TAMIETTO JUNIOR UNIPESSOAL LDA';
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_phys',
                'type' => 'Physical',
                'status' => 'Created',
                'name' => 'Physical Card',
            ]);
        $gateway->shouldNotReceive('activateCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);

        $this->assertSame('card_phys', $card->wallester_card_id);
        $this->assertSame('Physical', $card->type);
        $this->assertSame('Created', $card->status);
        $this->assertNull($card->activated_at);
        $this->assertDatabaseHas('customer_card_delivery_addresses', [
            'customer_id' => $customer->id,
            'customer_wallester_card_id' => $card->id,
            'address' => 'Rua das Flores 1',
            'city' => 'Lisboa',
            'state' => 'Lisboa',
            'country' => 'PT',
            'first_name' => 'Joao',
            'last_name' => 'Silva',
            'phone' => '+351912345678',
            'postal_code' => '1000-001',
        ]);
    }

    public function test_create_physical_card_uses_cheapest_allowed_dispatch_method(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys_dhl');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardDispatchMethods')
            ->once()
            ->with('PRT', (string) $customer->id)
            ->andReturn([
                [
                    'dispatch_method' => 'DHLExpress',
                    'price' => 20.0,
                    'description' => 'Express',
                ],
                [
                    'dispatch_method' => 'DHLGlobalMailTracked',
                    'price' => 8.5,
                    'description' => 'Tracked',
                ],
                [
                    'dispatch_method' => 'DHLExpress',
                    'price' => 20.0,
                    'description' => 'Express dup',
                ],
            ]);
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['type'] === 'ChipAndPin'
                    && $payload['delivery_address']['dispatch_method'] === 'DHLGlobalMailTracked';
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_phys_cheap',
                'type' => 'ChipAndPin',
                'status' => 'Created',
                'name' => 'Physical Card',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);

        $this->assertSame('card_phys_cheap', $card->wallester_card_id);
    }

    public function test_create_physical_card_fails_when_no_dispatch_methods_are_available(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys_no_dispatch');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardDispatchMethods')->once()->andReturn([]);
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester has no dispatch methods for country [PRT].');

        $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);
    }

    public function test_create_physical_card_requires_delivery_address(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys_no_addr');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Delivery address is required to create a physical card.');

        $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
        ]);
    }

    public function test_activate_virtual_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_activate');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_activate_1',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_activate_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_activate_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'masked_card_number' => '****9999',
                'activated_at' => '2026-07-01T12:30:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->activateVirtualCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
        $this->assertSame('****9999', $card->masked_card_number);
        $this->assertNotNull($card->activated_at);
    }

    public function test_activate_virtual_card_is_idempotent_when_already_active(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_active');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_active_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
            'activated_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->activateVirtualCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
    }

    public function test_create_card_for_customer_allows_additional_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_existing');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_second',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Second Card',
        ]);
        $gateway->shouldNotReceive('activateCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ]);

        $this->assertSame('card_second', $card->wallester_card_id);
    }

    public function test_create_card_for_customer_allows_new_card_when_existing_is_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_closed');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Old Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_new',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'New Card',
        ]);
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'New Card',
        ]);

        $this->assertSame('card_new', $card->wallester_card_id);
    }

    public function test_create_additional_virtual_card_charges_fee_when_balance_covers_it(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_card',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_fee_from');
        $this->createWallesterAccount($this->createRetailCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_card');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_fee',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_fee_from', (string) $customer->id)
            ->andReturn(['available_amount' => 5.00, 'balance' => 5.00]);
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_paid_second',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Second Card',
        ]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['from_account_id'] === $account->wallester_account_id
                    && $payload['to_account_id'] === 'wal_fee_card'
                    && $payload['amount'] === 5.0
                    && $payload['description'] === 'Taxa de emissão de cartão virtual';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_card_fee']);
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_paid_second', (string) $customer->id)
            ->andReturn([
                'id' => 'card_paid_second',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Second Card',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ]);

        $this->assertSame('card_paid_second', $card->wallester_card_id);
        $this->assertSame('Active', $card->status);
    }

    public function test_create_additional_virtual_card_rejects_insufficient_balance(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_fee_low');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_low',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Frozen Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_fee_low', (string) $customer->id)
            ->andReturn(['available_amount' => 4.99, 'balance' => 4.99]);
        $gateway->shouldNotReceive('createCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        try {
            $service->createCardForCustomer($customer, [
                'type' => 'Virtual',
                'name' => 'Second Card',
            ]);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCode::INSUFFICIENT_BALANCE, $exception->reason);
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function test_create_additional_physical_card_charges_fee_when_balance_covers_it(): void
    {
        $this->seedWallesterSettings([
            'wallester.physical_card_value' => '1500',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_phys',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_fee_phys_from');
        $this->createWallesterAccount($this->createRetailCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_phys');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_phys_fee',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_fee_phys_from', (string) $customer->id)
            ->andReturn(['available_amount' => 20.00, 'balance' => 20.00]);
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_paid_phys',
            'type' => 'Physical',
            'status' => 'Created',
            'name' => 'Physical Card',
        ]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['from_account_id'] === $account->wallester_account_id
                    && $payload['to_account_id'] === 'wal_fee_phys'
                    && $payload['amount'] === 10.0
                    && $payload['description'] === 'Taxa de emissão de cartão físico';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_phys_fee']);
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);

        $this->assertSame('card_paid_phys', $card->wallester_card_id);
        $this->assertSame('Created', $card->status);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'physical_card_issuance_fee',
            'amount' => 1500,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => 'physical_card_issuance_fee',
            'amount' => 1500,
            'idempotency_key' => 'physical-card-issuance-fee:'.$customer->id.':'.$card->id.':out',
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => 'physical_card_issuance_fee',
            'amount' => 1000,
            'idempotency_key' => 'physical-card-platform-fee:'.$customer->id.':'.$card->id.':out',
        ]);
    }

    public function test_create_first_physical_card_charges_remaining_fee_after_dispatch(): void
    {
        $this->seedWallesterSettings([
            'wallester.physical_card_value' => '850',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_first_phys',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_first_phys_from');
        $this->createWallesterAccount($this->createRetailCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_first_phys');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_first_phys_from', (string) $customer->id)
            ->andReturn(['available_amount' => 20.00, 'balance' => 20.00]);
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_first_phys',
            'type' => 'ChipAndPin',
            'status' => 'Created',
            'name' => 'Physical Card',
        ]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['from_account_id'] === $account->wallester_account_id
                    && $payload['to_account_id'] === 'wal_fee_first_phys'
                    && $payload['amount'] === 3.5
                    && $payload['description'] === 'Taxa de emissão de cartão físico';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_first_phys_fee']);
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);

        $this->assertSame('card_first_phys', $card->wallester_card_id);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'physical_card_issuance_fee',
            'amount' => 850,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => 'physical_card_issuance_fee',
            'amount' => 850,
            'idempotency_key' => 'physical-card-issuance-fee:'.$customer->id.':'.$card->id.':out',
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => 'physical_card_issuance_fee',
            'amount' => 350,
            'idempotency_key' => 'physical-card-platform-fee:'.$customer->id.':'.$card->id.':out',
        ]);
    }

    public function test_create_physical_card_does_not_transfer_when_dispatch_covers_settings(): void
    {
        $this->seedWallesterSettings([
            'wallester.physical_card_value' => '850',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_phys_full',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys_full_from');
        $this->createWallesterAccount($this->createRetailCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_phys_full');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardDispatchMethods')
            ->once()
            ->andReturn([
                [
                    'dispatch_method' => 'DHLGlobalMailTracked',
                    'price' => 8.5,
                    'description' => 'Tracked',
                ],
            ]);
        $gateway->shouldReceive('getAccount')
            ->with('wal_phys_full_from', (string) $customer->id)
            ->andReturn(['available_amount' => 20.00, 'balance' => 20.00]);
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_phys_full',
            'type' => 'ChipAndPin',
            'status' => 'Created',
            'name' => 'Physical Card',
            'delivery_address' => [
                'dispatch_method' => 'DHLGlobalMailTracked',
                'country_code' => 'PRT',
            ],
        ]);
        $gateway->shouldNotReceive('accountTransfer');
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Physical',
            'name' => 'Physical Card',
            'delivery_address' => [
                'address' => 'Rua das Flores 1',
                'city' => 'Lisboa',
                'state' => 'Lisboa',
                'country' => 'PT',
                'first_name' => 'Joao',
                'last_name' => 'Silva',
                'phone' => '+351912345678',
                'postal_code' => '1000-001',
            ],
        ]);

        $this->assertSame('card_phys_full', $card->wallester_card_id);
        $visibleMovement = FundingPartnerMovement::query()
            ->where('idempotency_key', 'physical-card-issuance-fee:'.$customer->id.':'.$card->id.':out')
            ->first();
        $this->assertNotNull($visibleMovement);
        $this->assertSame(850, (int) $visibleMovement->amount);
        $this->assertNull($visibleMovement->fees_amount);
        $this->assertNull($visibleMovement->platform_fees_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'physical_card_issuance_fee',
            'amount' => 850,
            'reference_type' => $visibleMovement->getMorphClass(),
            'reference_id' => $visibleMovement->id,
        ]);
        $this->assertDatabaseMissing('funding_partner_movements', [
            'idempotency_key' => 'physical-card-platform-fee:'.$customer->id.':'.$card->id.':out',
        ]);
    }

    public function test_create_additional_physical_card_rejects_insufficient_balance(): void
    {
        $this->seedWallesterSettings([
            'wallester.physical_card_value' => '1500',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_phys_low');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_phys_low',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_phys_low', (string) $customer->id)
            ->andReturn(['available_amount' => 10.00, 'balance' => 10.00]);
        $gateway->shouldNotReceive('createCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        try {
            $service->createCardForCustomer($customer, [
                'type' => 'Physical',
                'name' => 'Physical Card',
                'delivery_address' => [
                    'address' => 'Rua das Flores 1',
                    'city' => 'Lisboa',
                    'state' => 'Lisboa',
                    'country' => 'PT',
                    'first_name' => 'Joao',
                    'last_name' => 'Silva',
                    'phone' => '+351912345678',
                    'postal_code' => '1000-001',
                ],
            ]);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCode::INSUFFICIENT_BALANCE, $exception->reason);
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function test_create_card_for_customer_requires_active_wallester_account(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING, 'wal_pending');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Wallester account is not active.');

        $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Main Card',
        ]);
    }

    public function test_create_card_requires_known_wallester_account(): void
    {
        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester account [missing_acc] was not found.');

        $service->createCard([
            'account_id' => 'missing_acc',
            'type' => 'Virtual',
        ]);
    }

    public function test_block_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_block_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('blockCard')
            ->once()
            ->with('card_block_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_block_1',
                'type' => 'Virtual',
                'status' => 'Blocked',
                'name' => 'Main Card',
                'block_type' => 'BlockedByCardUser',
                'blocked_at' => '2026-09-03T10:00:00Z',
                'blocked_by' => (string) $customer->id,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->blockCardForCustomer($customer, $localCard);

        $this->assertSame('Blocked', $card->status);
        $this->assertSame('BlockedByCardUser', $card->block_type);
        $this->assertNotNull($card->blocked_at);
    }

    public function test_block_card_for_customer_is_idempotent_when_already_blocked(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_blocked');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_blocked_1',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Main Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');

        $service = $this->makeService($gateway);

        $card = $service->blockCardForCustomer($customer, $localCard);

        $this->assertSame('Blocked', $card->status);
        $this->assertSame('BlockedByCardUser', $card->block_type);
    }

    public function test_block_card_for_customer_rejects_non_active_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_block',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be blocked in its current status.');

        $service->blockCardForCustomer($customer, $localCard);
    }

    public function test_unblock_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_1',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Main Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('unblockCard')
            ->once()
            ->with('card_unblock_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_unblock_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'block_type' => null,
                'blocked_at' => null,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->unblockCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
        $this->assertNull($card->block_type);
        $this->assertNull($card->blocked_at);
    }

    public function test_unblock_card_for_customer_is_idempotent_when_already_active(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock_active');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_active',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');

        $service = $this->makeService($gateway);

        $card = $service->unblockCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
    }

    public function test_unblock_card_for_customer_rejects_non_blocked_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_unblock',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be unblocked in its current status.');

        $service->unblockCardForCustomer($customer, $localCard);
    }

    public function test_close_card_for_customer_updates_local_card_when_wallester_returns_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_close_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_close_1',
                'type' => 'Virtual',
                'status' => 'Closed',
                'name' => 'Main Card',
                'close_reason' => 'ClosedByCardholder',
                'closed_at' => '2026-09-03T10:00:00Z',
                'closed_by' => (string) $customer->id,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->closeCardForCustomer($customer, $localCard);

        $this->assertSame('Closed', $card->status);
        $this->assertSame('ClosedByCardholder', $card->close_reason);
        $this->assertNotNull($card->closed_at);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_close_card_for_customer_does_not_persist_when_status_is_not_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close_no_confirm');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_no_confirm',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_close_no_confirm', (string) $customer->id)
            ->andReturn([
                'id' => 'card_close_no_confirm',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
            ]);

        $service = $this->makeService($gateway);

        try {
            $service->closeCardForCustomer($customer, $localCard);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wallester card closing did not confirm a closed card.', $exception->getMessage());
        }

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Active',
            'close_reason' => null,
        ]);
    }

    public function test_close_card_for_customer_is_idempotent_when_already_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_closed');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_closed_1',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Main Card',
            'close_reason' => 'ClosedByCardholder',
            'closed_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');

        $service = $this->makeService($gateway);

        $card = $service->closeCardForCustomer($customer, $localCard);

        $this->assertSame('Closed', $card->status);
        $this->assertSame('ClosedByCardholder', $card->close_reason);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_close_card_for_customer_rejects_created_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_close',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be closed in its current status.');

        $service->closeCardForCustomer($customer, $localCard);
    }

    public function test_get_encrypted_pin_for_customer_decrypts_wallester_payload(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_pin');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_pin_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getEncryptedPinCard')
            ->once()
            ->with('card_pin_1', 'public-key-base64', (string) $customer->id)
            ->andReturn('encrypted-pin');

        $rsaKeys = Mockery::mock(WallesterCardRsaKeyService::class);
        $rsaKeys->shouldReceive('publicKeyBase64')->once()->with('EUR')->andReturn('public-key-base64');

        $decryptor = Mockery::mock(WallesterSensitiveDataDecryptor::class);
        $decryptor->shouldReceive('decryptPin')
            ->once()
            ->with('encrypted-pin', 'EUR')
            ->andReturn('1234');

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
            app(WallesterSettingsService::class),
            app(WallesterFundingTransferDriver::class),
            app(FundingPartnerMovementService::class),
            app(LedgerService::class),
            app(SettingsService::class),
            $rsaKeys,
            $decryptor,
        );

        $this->assertSame('1234', $service->getEncryptedPinForCustomer($customer, $card));
    }

    public function test_get_encrypted_pin_for_customer_rejects_inactive_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_pin_inactive');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_pin_inactive',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('getEncryptedPinCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Sensitive card data is only available for active cards.');

        $service->getEncryptedPinForCustomer($customer, $card);
    }

    public function test_link_card_to_another_customer_moves_local_card_and_counts(): void
    {
        $owner = $this->createRetailCustomer();
        $target = $this->createRetailCustomer();
        $sourceAccount = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_link_from', [
            'cards_count' => 1,
        ]);
        $targetAccount = $this->createWallesterAccount($target, IntegrationAccountStatus::ACTIVE, 'wal_link_to');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $sourceAccount->id,
            'wallester_card_id' => 'card_link_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('linkCardToAccount')
            ->once()
            ->with('card_link_1', 'wal_link_to', (string) $owner->id)
            ->andReturn([
                'id' => 'card_link_1',
                'account_id' => 'wal_link_to',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
            ]);

        $service = $this->makeService($gateway);
        $linked = $service->linkCardToAnotherCustomer($owner, $localCard, $target);

        $this->assertSame($targetAccount->id, $linked->customer_wallester_account_id);
        $this->assertSame(0, $sourceAccount->fresh()->cards_count);
        $this->assertSame(1, $targetAccount->fresh()->cards_count);
    }

    public function test_link_card_to_another_customer_rejects_closed_card(): void
    {
        $owner = $this->createRetailCustomer();
        $target = $this->createRetailCustomer();
        $sourceAccount = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_link_closed');
        $this->createWallesterAccount($target, IntegrationAccountStatus::ACTIVE, 'wal_link_closed_to');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $sourceAccount->id,
            'wallester_card_id' => 'card_link_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be linked in its current status.');

        $service->linkCardToAnotherCustomer($owner, $localCard, $target);
    }

    public function test_link_card_to_another_customer_rejects_same_customer(): void
    {
        $owner = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_link_same');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_link_same',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Cannot link a card to the same customer.');

        $service->linkCardToAnotherCustomer($owner, $localCard, $owner);
    }

    public function test_link_card_to_another_customer_rejects_inactive_target_account(): void
    {
        $owner = $this->createRetailCustomer();
        $target = $this->createRetailCustomer();
        $sourceAccount = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_link_inactive_from');
        $this->createWallesterAccount($target, IntegrationAccountStatus::FAILED, 'wal_link_inactive_to');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $sourceAccount->id,
            'wallester_card_id' => 'card_link_inactive',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Wallester account not found or not active for the target customer.');

        $service->linkCardToAnotherCustomer($owner, $localCard, $target);
    }

    private function makeService($gateway): WallesterService
    {
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        return new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
            app(WallesterSettingsService::class),
            app(WallesterFundingTransferDriver::class),
            app(FundingPartnerMovementService::class),
            app(LedgerService::class),
            app(SettingsService::class),
            app(WallesterCardRsaKeyService::class),
            app(WallesterSensitiveDataDecryptor::class),
        );
    }
}
