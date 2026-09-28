<?php

namespace Tests\Feature\Domains\Push;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Services\CustomerNotificationService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerPushTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->credentialsPath = storage_path('framework/testing-fcm.json');
        $this->writeCredentials();
        config(['services.fcm.credentials' => $this->credentialsPath]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_register_enables_push_only_when_token_is_present(): void
    {
        $customer = $this->createActiveCustomer();
        $device = $this->createDevice($customer, 'device-push-1');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/push/devices', [
                'device_token' => 'device-push-1',
                'platform' => 'ios',
                'push_token' => 'fcm-token-1',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $device->refresh();
        $this->assertSame('fcm-token-1', $device->push_token);
        $this->assertSame('ios', $device->platform);
        $this->assertTrue($device->push_enabled);
        $this->assertNotNull($device->push_token_seen_at);
        $this->assertNotNull($device->pin);
    }

    public function test_register_web_without_token_keeps_push_disabled(): void
    {
        $customer = $this->createActiveCustomer();
        $device = $this->createDevice($customer, 'device-web-1');

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->putJson('/api/push/devices', [
                'device_token' => 'device-web-1',
                'platform' => 'web',
            ])
            ->assertOk();

        $device->refresh();
        $this->assertSame('web', $device->platform);
        $this->assertNull($device->push_token);
        $this->assertFalse($device->push_enabled);
    }

    public function test_register_moves_the_same_push_token_off_the_previous_device(): void
    {
        $customer = $this->createActiveCustomer();
        $first = $this->createDevice($customer, 'device-a');
        $second = $this->createDevice($customer, 'device-b');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/push/devices', [
            'device_token' => 'device-a',
            'platform' => 'android',
            'push_token' => 'shared-fcm',
        ])->assertOk();

        $this->withToken($token)->putJson('/api/push/devices', [
            'device_token' => 'device-b',
            'platform' => 'android',
            'push_token' => 'shared-fcm',
        ])->assertOk();

        $first->refresh();
        $second->refresh();
        $this->assertNull($first->push_token);
        $this->assertFalse($first->push_enabled);
        $this->assertSame('shared-fcm', $second->push_token);
        $this->assertTrue($second->push_enabled);
    }

    public function test_register_requires_an_existing_device(): void
    {
        $customer = $this->createActiveCustomer();

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->putJson('/api/push/devices', [
                'device_token' => 'missing',
                'platform' => 'ios',
                'push_token' => 'fcm-token',
            ])
            ->assertNotFound()
            ->assertJsonPath('reason', 'device_not_found');
    }

    public function test_disable_clears_push_and_keeps_the_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $device = $this->createDevice($customer, 'device-off');
        $device->update([
            'platform' => 'ios',
            'push_token' => 'fcm-off',
            'push_enabled' => true,
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->deleteJson('/api/push/devices', [
                'device_token' => 'device-off',
            ])
            ->assertOk();

        $device->refresh();
        $this->assertNull($device->push_token);
        $this->assertFalse($device->push_enabled);
        $this->assertNotNull($device->pin);
        $this->assertTrue(Hash::check('1234', (string) $device->pin));
    }

    public function test_notification_sends_push_only_for_enabled_devices(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/yoop-test/messages/1']),
        ]);

        $customer = $this->createActiveCustomer();
        $enabled = $this->createDevice($customer, 'device-on');
        $disabled = $this->createDevice($customer, 'device-disabled');
        $enabled->update([
            'platform' => 'ios',
            'push_token' => 'fcm-on',
            'push_enabled' => true,
        ]);
        $disabled->update([
            'platform' => 'android',
            'push_token' => 'fcm-off',
            'push_enabled' => false,
        ]);

        app(CustomerNotificationService::class)->notify(
            $customer,
            'wallester.3ds_challenge',
            'Confirm online payment',
            'Shop — 10 EUR',
            ['challenge_id' => 15],
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $message = $request->data()['message'];

            return $message['token'] === 'fcm-on'
                && $message['notification']['title'] === 'Confirm online payment'
                && $message['data']['type'] === 'wallester.3ds_challenge'
                && $message['data']['challenge_id'] === '15'
                && $message['data']['notification_id'] !== '';
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'fcm.googleapis.com')
            && ($request->data()['message']['token'] ?? null) === 'fcm-off');
    }

    public function test_notification_push_includes_resource_id_and_path(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/yoop-test/messages/1']),
        ]);

        $customer = $this->createActiveCustomer();
        $device = $this->createDevice($customer, 'device-transfer');
        $device->update([
            'platform' => 'android',
            'push_token' => 'fcm-transfer',
            'push_enabled' => true,
        ]);

        app(CustomerNotificationService::class)->notify(
            $customer,
            CustomerNotificationType::TransferReceived,
            'Recebeste uma transferência',
            '10,00 EUR de João',
            resourceId: 155,
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $message = $request->data()['message'];

            return $message['token'] === 'fcm-transfer'
                && $message['data']['type'] === 'transfer_received'
                && $message['data']['resource_id'] === '155'
                && $message['data']['path'] === '/api/wallet/transactions/155'
                && $message['data']['notification_id'] !== '';
        });
    }

    public function test_invalid_fcm_token_is_cleared_and_the_pin_remains(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
        ]);

        $customer = $this->createActiveCustomer();
        $device = $this->createDevice($customer, 'device-stale');
        $device->update([
            'platform' => 'android',
            'push_token' => 'fcm-stale',
            'push_enabled' => true,
        ]);

        app(CustomerNotificationService::class)->notify(
            $customer,
            'wallet.received',
            'Pagamento recebido',
            null,
            [],
        );

        $device->refresh();
        $this->assertNull($device->push_token);
        $this->assertFalse($device->push_enabled);
        $this->assertTrue(Hash::check('1234', (string) $device->pin));
    }

    public function test_forget_device_removes_only_that_device_and_the_current_token(): void
    {
        $customer = $this->createActiveCustomer();
        $kept = $this->createDevice($customer, 'device-kept');
        $this->createDevice($customer, 'device-gone');
        $current = $customer->createToken('current')->plainTextToken;
        $other = $customer->createToken('other')->plainTextToken;

        $this->withToken($current)
            ->deleteJson('/api/auth/devices', [
                'device_token' => 'device-gone',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(CustomerDevice::query()->where('device_token', 'device-gone')->first());
        $this->assertNotNull($kept->fresh());
        $this->assertNull(PersonalAccessToken::findToken($current));
        $this->assertNotNull(PersonalAccessToken::findToken($other));

        $this->app['auth']->forgetGuards();

        $this->withToken($current)
            ->putJson('/api/push/devices', [
                'device_token' => 'device-kept',
                'platform' => 'web',
            ])
            ->assertUnauthorized();

        $this->withToken($other)
            ->putJson('/api/push/devices', [
                'device_token' => 'device-kept',
                'platform' => 'web',
            ])
            ->assertOk();
    }

    private function createActiveCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }

    private function createDevice(Customer $customer, string $deviceToken): CustomerDevice
    {
        return CustomerDevice::query()->create([
            'customer_id' => $customer->id,
            'device_name' => 'Test Device',
            'device_token' => $deviceToken,
            'pin' => Hash::make('1234'),
        ]);
    }

    private function writeCredentials(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKey);

        file_put_contents($this->credentialsPath, json_encode([
            'project_id' => 'yoop-test',
            'client_email' => 'fcm@yoop-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));
    }
}
