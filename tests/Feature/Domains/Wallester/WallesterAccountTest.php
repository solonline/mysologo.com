<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterAccountTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_customer_can_list_all_wallester_accounts(): void
    {
        $customer = $this->createActiveCustomer();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listAccounts')
            ->once()
            ->with(['from_record' => 0, 'records_count' => 1000], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 2,
                'accounts' => [
                    [
                        'id' => '8951efcc-91a7-4f59-977f-c9ad186bfa98',
                        'currency_code' => 'EUR',
                        'balance' => 150.25,
                    ],
                    [
                        'id' => '8951efcc-91a7-4f59-977f-c9ad186bfa99',
                        'currency_code' => 'USD',
                        'balance' => 0,
                    ],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/accounts')
            ->assertOk()
            ->assertJsonPath('meta.total_records_number', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', '8951efcc-91a7-4f59-977f-c9ad186bfa98')
            ->assertJsonPath('data.1.currency_code', 'USD');
    }

    public function test_customer_fetches_all_pages_of_wallester_accounts(): void
    {
        $customer = $this->createActiveCustomer();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listAccounts')
            ->once()
            ->with(['from_record' => 0, 'records_count' => 1000], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 3,
                'accounts' => [
                    ['id' => 'wal_page_1'],
                    ['id' => 'wal_page_2'],
                ],
            ]);
        $gateway->shouldReceive('listAccounts')
            ->once()
            ->with(['from_record' => 2, 'records_count' => 1000], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 3,
                'accounts' => [
                    ['id' => 'wal_page_3'],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/accounts')
            ->assertOk()
            ->assertJsonPath('meta.total_records_number', 3)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.id', 'wal_page_3');
    }

    public function test_customer_can_filter_wallester_accounts(): void
    {
        $customer = $this->createActiveCustomer();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listAccounts')
            ->once()
            ->with([
                'name' => 'Travel',
                'order_direction' => 'desc',
                'from_record' => 0,
                'records_count' => 1000,
            ], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 1,
                'accounts' => [
                    ['id' => 'wal_filtered', 'name' => 'Travel'],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/accounts?name=Travel&order_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Travel');
    }

    public function test_list_accounts_validates_order_field(): void
    {
        $customer = $this->createActiveCustomer();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/accounts?order_field=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_field']);
    }

    public function test_list_accounts_requires_authentication(): void
    {
        $this->getJson('/api/wallester/accounts')
            ->assertUnauthorized();
    }

    public function test_customer_can_show_wallester_account_by_id(): void
    {
        $customer = $this->createActiveCustomer();
        $accountId = '27c0b35d-6746-4528-b91c-11e10d851f29';

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with($accountId, (string) $customer->id)
            ->andReturn([
                'id' => $accountId,
                'currency_code' => 'EUR',
                'name' => 'Test Account',
                'balance' => 250.00,
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/accounts/'.$accountId)
            ->assertOk()
            ->assertJsonPath('data.id', $accountId)
            ->assertJsonPath('data.name', 'Test Account')
            ->assertJsonPath('data.balance', 250);
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
