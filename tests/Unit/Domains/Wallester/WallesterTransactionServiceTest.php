<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterTransactionService;
use App\Exceptions\ApiException;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterTransactionServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_list_for_customer_uses_account_transactions_endpoint(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_acc');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listAccountTransactions')
            ->once()
            ->with('wal_tx_acc', ['from_record' => 0, 'records_count' => 15], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 1,
                'transactions' => [
                    ['id' => 'tx_1', 'account_id' => 'wal_tx_acc', 'amount' => -10.5],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(WallesterTransactionService::class)->listForCustomer($customer, []);

        $this->assertSame(1, $result['total_records_number']);
        $this->assertSame('tx_1', $result['transactions'][0]['id']);
        $this->assertSame(0, $result['from_record']);
        $this->assertSame(15, $result['records_count']);
    }

    public function test_list_for_customer_uses_card_transactions_when_card_id_is_provided(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_card_acc');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'wal_card_tx_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardTransactions')
            ->once()
            ->with('wal_card_tx_1', ['from_record' => 5, 'records_count' => 20], (string) $customer->id)
            ->andReturn([
                'total_records_number' => 2,
                'transactions' => [
                    ['id' => 'tx_card_1', 'card_id' => 'wal_card_tx_1'],
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(WallesterTransactionService::class)->listForCustomer($customer, [
            'card_id' => $card->id,
            'from_record' => 5,
            'records_count' => 20,
        ]);

        $this->assertSame(2, $result['total_records_number']);
        $this->assertSame('tx_card_1', $result['transactions'][0]['id']);
    }

    public function test_show_for_customer_rejects_foreign_transaction(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_show_acc');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getTransaction')
            ->once()
            ->with('tx_foreign', (string) $customer->id)
            ->andReturn([
                'id' => 'tx_foreign',
                'account_id' => 'other_account',
                'card_id' => 'other_card',
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This transaction does not belong to the authenticated customer.');

        app(WallesterTransactionService::class)->showForCustomer($customer, 'tx_foreign');
    }

    public function test_show_for_customer_allows_transaction_by_account_id(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_tx_owned_acc');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getTransaction')
            ->once()
            ->with('tx_owned', (string) $customer->id)
            ->andReturn([
                'id' => 'tx_owned',
                'account_id' => 'wal_tx_owned_acc',
                'amount' => -5.0,
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $transaction = app(WallesterTransactionService::class)->showForCustomer($customer, 'tx_owned');

        $this->assertSame('tx_owned', $transaction['id']);
    }
}
