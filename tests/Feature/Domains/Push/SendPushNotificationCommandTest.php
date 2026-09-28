<?php

namespace Tests\Feature\Domains\Push;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Push\Jobs\SendCustomerPushJob;
use App\Models\CustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class SendPushNotificationCommandTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_command_sends_push_only_to_the_given_customer_ids(): void
    {
        Bus::fake([SendCustomerPushJob::class]);

        $included = $this->createRetailCustomer();
        $alsoIncluded = $this->createRetailCustomer();
        $excluded = $this->createRetailCustomer();

        $this->artisan('push:send', [
            'ids' => [$included->id, $alsoIncluded->id],
            '--title' => 'Aviso',
            '--body' => 'Só para estes clientes',
            '--type' => CustomerNotificationType::TestPush->value,
        ])->assertSuccessful();

        $this->assertSame(2, CustomerNotification::query()->count());
        $this->assertTrue(
            CustomerNotification::query()
                ->where('customer_id', $included->id)
                ->where('title', 'Aviso')
                ->where('type', CustomerNotificationType::TestPush->value)
                ->exists()
        );
        $this->assertTrue(
            CustomerNotification::query()->where('customer_id', $alsoIncluded->id)->exists()
        );
        $this->assertFalse(
            CustomerNotification::query()->where('customer_id', $excluded->id)->exists()
        );

        Bus::assertDispatchedTimes(SendCustomerPushJob::class, 2);
    }

    public function test_command_warns_when_a_customer_id_does_not_exist_but_sends_to_valid_ones(): void
    {
        Bus::fake([SendCustomerPushJob::class]);

        $customer = $this->createRetailCustomer();

        $this->artisan('push:send', [
            'ids' => [$customer->id, 999999],
            '--title' => 'Aviso',
        ])->assertSuccessful();

        $this->assertSame(1, CustomerNotification::query()->count());
        Bus::assertDispatchedTimes(SendCustomerPushJob::class, 1);
    }

    public function test_command_rejects_unknown_type(): void
    {
        Bus::fake([SendCustomerPushJob::class]);

        $customer = $this->createRetailCustomer();

        $this->artisan('push:send', [
            'ids' => [$customer->id],
            '--type' => 'not_a_real_type',
        ])->assertFailed();

        $this->assertSame(0, CustomerNotification::query()->count());
        Bus::assertNothingDispatched();
    }

    public function test_command_stores_resource_id_and_path_for_transfer_received(): void
    {
        Bus::fake([SendCustomerPushJob::class]);

        $customer = $this->createRetailCustomer();

        $this->artisan('push:send', [
            'ids' => [$customer->id],
            '--title' => 'Recebeste uma transferência',
            '--type' => CustomerNotificationType::TransferReceived->value,
            '--resource-id' => '155',
        ])->assertSuccessful();

        $notification = CustomerNotification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame(CustomerNotificationType::TransferReceived->value, $notification->type);
        $this->assertSame('155', $notification->data['resource_id']);
        $this->assertSame('api/wallet/transactions/155', $notification->data['path']);
    }
}