<?php

namespace Tests\Unit\Domains\Customer;

use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPhoneChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(['status' => 'success', 'body' => null, 'http_code' => 202]);
        });
    }

    public function test_unverified_current_phone_requires_sms_confirmation(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $result = app(CustomerPhoneChangeService::class)->requestChange($customer, '351922222222');

        $customer->refresh();

        $this->assertSame('pending_verification', $result['status']);
        $this->assertSame('351911111111', $customer->phone);
        $this->assertFalse($result['phone_verified']);
        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351922222222',
            'verified_at' => null,
        ]);
    }

    public function test_confirm_change_updates_unverified_phone_after_valid_code(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351922222222',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $updated = app(CustomerPhoneChangeService::class)->confirmChange($customer, '351922222222', '654321');

        $this->assertSame('351922222222', $updated->phone);
        $this->assertTrue(app(CustomerPhoneChangeService::class)->isCurrentPhoneVerified($updated));
    }

    public function test_verified_current_phone_requires_sms_confirmation(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '123456',
            'verified_at' => now(),
        ]);

        $result = app(CustomerPhoneChangeService::class)->requestChange($customer, '351933333333');

        $customer->refresh();

        $this->assertSame('pending_verification', $result['status']);
        $this->assertSame('351911111111', $customer->phone);
        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351933333333',
            'verified_at' => null,
        ]);
    }

    public function test_confirm_change_updates_phone_after_valid_code(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '111111',
            'verified_at' => now(),
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351944444444',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $updated = app(CustomerPhoneChangeService::class)->confirmChange($customer, '351944444444', '654321');

        $this->assertSame('351944444444', $updated->phone);
        $this->assertTrue(app(CustomerPhoneChangeService::class)->isCurrentPhoneVerified($updated));
    }

    public function test_confirm_pending_change_uses_latest_pending_phone(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '111111',
            'verified_at' => now(),
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351955555555',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $updated = app(CustomerPhoneChangeService::class)->confirmPendingChange($customer, '654321');

        $this->assertSame('351955555555', $updated->phone);
    }

    public function test_request_change_rejects_same_phone(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
        ]);

        $this->expectException(ApiException::class);

        app(CustomerPhoneChangeService::class)->requestChange($customer, '351911111111');
    }

    public function test_cancel_pending_change_removes_unverified_target_phone(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '111111',
            'verified_at' => now(),
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351955555555',
            'code' => '654321',
            'verified_at' => null,
        ]);

        app(CustomerPhoneChangeService::class)->cancelPendingChange($customer);

        $this->assertNull(app(CustomerPhoneChangeService::class)->pendingChangePhone($customer));
        $this->assertDatabaseMissing('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351955555555',
        ]);
    }

    public function test_request_change_replaces_existing_pending_phone(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '111111',
            'verified_at' => now(),
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351955555555',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $result = app(CustomerPhoneChangeService::class)->requestChange($customer, '351966666666');

        $this->assertSame('pending_verification', $result['status']);
        $this->assertSame('351966666666', app(CustomerPhoneChangeService::class)->pendingChangePhone($customer));
        $this->assertDatabaseMissing('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351955555555',
        ]);
    }

    public function test_resend_pending_change_sends_sms_to_pending_phone(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '111111',
            'verified_at' => now(),
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351955555555',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $result = app(CustomerPhoneChangeService::class)->resendPendingChange($customer);

        $this->assertSame('351955555555', $result['phone']);
        $this->assertSame('pending_verification', $result['status']);
    }
}
