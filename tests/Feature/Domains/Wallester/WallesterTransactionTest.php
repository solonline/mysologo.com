<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterTransactionTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_customer_can_list_account_transactions(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_api_acc');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listAccountTransactions')
            ->once()
            ->with('wal_tx_api_acc', ['from_record' => 0, 'records_count' => 15], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 1,
                'transactions' => [
                    [
                        'id' => '8951efcc-91a7-4f59-977f-c9ad186bfa98',
                        'account_id' => 'wal_tx_api_acc',
                        'amount' => -12.34,
                        'currency_code' => 'EUR',
                    ],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total_records_number', 1)
            ->assertJsonPath('data.0.id', '8951efcc-91a7-4f59-977f-c9ad186bfa98')
            ->assertJsonPath('data.0.amount', -12.34);
    }

    public function test_customer_can_list_card_transactions_with_card_filter(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_card_filter');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'wal_card_filter_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardTransactions')
            ->once()
            ->with('wal_card_filter_1', ['from_record' => 0, 'records_count' => 10], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 1,
                'transactions' => [
                    ['id' => 'tx_card_filter', 'card_id' => 'wal_card_filter_1'],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/transactions?card_id='.$card->id.'&records_count=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'tx_card_filter');
    }

    public function test_customer_can_show_owned_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_show_api');

        $transactionId = '8951efcc-91a7-4f59-977f-c9ad186bfa98';

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getTransaction')
            ->once()
            ->with($transactionId, (string) $customer->id)
            ->andReturn([
                'id' => $transactionId,
                'account_id' => 'wal_tx_show_api',
                'merchant' => ['name' => 'Coffee Shop'],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/transactions/'.$transactionId)
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.merchant.name', 'Coffee Shop');
    }

    public function test_customer_cannot_show_foreign_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_intruder_acc');

        $transactionId = '8951efcc-91a7-4f59-977f-c9ad186bfa99';

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getTransaction')
            ->once()
            ->with($transactionId, (string) $customer->id)
            ->andReturn([
                'id' => $transactionId,
                'account_id' => 'another_account',
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/transactions/'.$transactionId)
            ->assertForbidden();
    }

    public function test_list_transactions_validates_card_id(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/transactions?card_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['card_id']);
    }

    private function createActiveCustomer()
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }
}
