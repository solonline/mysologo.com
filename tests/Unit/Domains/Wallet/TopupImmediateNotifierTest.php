<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Jobs\SendCustomerNotificationEmailJob;
use App\Domains\Push\Jobs\SendCustomerPushJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\TopupImmediateNotifier;
use App\Models\CustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class TopupImmediateNotifierTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_notifier_dispatches_notification_for_bank_transfer_cash_balance_topup(): void
    {
        Bus::fake([SendCustomerPushJob::class, SendCustomerNotificationEmailJob::class]);

        $customer = $this->createRetailCustomer(['email' => 'topup@example.com']);

        $transaction = \App\Models\StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 9_900,
            'wallester_funding_net_amount' => 9_900,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_notify_1',
            'partner_funding_status' => PartnerFundingStatus::Completed,
        ]);

        app(TopupImmediateNotifier::class)->notifyIfEligible($transaction);

        $notification = CustomerNotification::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame(CustomerNotificationType::TopupImmediate->value, $notification->type);
        $this->assertSame((string) $transaction->id, (string) ($notification->data['resource_id'] ?? ''));

        Bus::assertDispatched(SendCustomerPushJob::class);
        Bus::assertDispatched(SendCustomerNotificationEmailJob::class, function (SendCustomerNotificationEmailJob $job) use ($notification): bool {
            return $job->notificationId === $notification->id;
        });
    }

    public function test_notifier_ignores_card_topups(): void
    {
        Bus::fake([SendCustomerPushJob::class, SendCustomerNotificationEmailJob::class]);

        $customer = $this->createRetailCustomer();

        $transaction = \App\Models\StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 4_875,
            'wallester_funding_net_amount' => 4_875,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Completed,
        ]);

        app(TopupImmediateNotifier::class)->notifyIfEligible($transaction);

        $this->assertDatabaseCount('customer_notifications', 0);
        Bus::assertNotDispatched(SendCustomerPushJob::class);
        Bus::assertNotDispatched(SendCustomerNotificationEmailJob::class);
    }
}
