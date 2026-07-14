<?php

namespace Tests\Feature\Domains\Auth;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class ResetPinTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_customer_can_reset_pin_with_current_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $device = $this->createDeviceWithPin($customer, 'device-reset-1', '1234');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/reset-pin', [
                'device_token' => 'device-reset-1',
                'current_pin' => '1234',
                'pin' => '5678',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'PIN updated successfully');

        $device->refresh();
        $this->assertTrue(Hash::check('5678', (string) $device->pin));
        $this->assertFalse(Hash::check('1234', (string) $device->pin));
    }

    public function test_customer_can_reset_pin_with_password(): void
    {
        $customer = $this->createActiveCustomer(['password' => Hash::make('secret-pass')]);
        $device = $this->createDeviceWithPin($customer, 'device-reset-2', '1234');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/reset-pin', [
                'device_token' => 'device-reset-2',
                'password' => 'secret-pass',
                'pin' => '9876',
            ])
            ->assertOk();

        $device->refresh();
        $this->assertTrue(Hash::check('9876', (string) $device->pin));
    }

    public function test_reset_pin_rejects_invalid_current_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createDeviceWithPin($customer, 'device-reset-3', '1234');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/reset-pin', [
                'device_token' => 'device-reset-3',
                'current_pin' => '0000',
                'pin' => '5678',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_pin');
    }

    public function test_reset_pin_rejects_same_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createDeviceWithPin($customer, 'device-reset-4', '1234');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/reset-pin', [
                'device_token' => 'device-reset-4',
                'current_pin' => '1234',
                'pin' => '1234',
            ])
            ->assertStatus(422);
    }

    public function test_reset_pin_requires_authentication(): void
    {
        $this->postJson('/api/auth/reset-pin', [
            'device_token' => 'device-reset-5',
            'current_pin' => '1234',
            'pin' => '5678',
        ])->assertUnauthorized();
    }

    private function createActiveCustomer(array $overrides = []): Customer
    {
        return $this->createRetailCustomer(array_merge([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ], $overrides));
    }

    private function createDeviceWithPin(Customer $customer, string $deviceToken, string $pin): CustomerDevice
    {
        return CustomerDevice::query()->create([
            'customer_id' => $customer->id,
            'device_name' => 'Test Device',
            'device_token' => $deviceToken,
            'pin' => Hash::make($pin),
        ]);
    }
}
