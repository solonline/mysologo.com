<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(['status' => 'success', 'body' => null, 'http_code' => 202]);
        });
    }

    public function test_send_without_phone_uses_customer_number(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $sent = app(PhoneVerificationService::class)->sendForOnboarding($customer);

        $this->assertTrue($sent);
        $this->assertSame('351911111111', $customer->fresh()->phone);
        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'verified_at' => null,
        ]);
    }

    public function test_send_with_new_phone_corrects_unverified_number(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $sent = app(PhoneVerificationService::class)->sendForOnboarding($customer, '351922222222');

        $this->assertTrue($sent);
        $this->assertSame('351922222222', $customer->fresh()->phone);
        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351922222222',
            'verified_at' => null,
        ]);
    }

    public function test_send_with_new_phone_is_rejected_when_current_is_verified(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '123456',
            'verified_at' => now(),
        ]);

        try {
            app(PhoneVerificationService::class)->sendForOnboarding($customer, '351922222222');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(ErrorCode::PHONE_ALREADY_VERIFIED, $e->reason);
            $this->assertSame(422, $e->status);
        }

        $this->assertSame('351911111111', $customer->fresh()->phone);
    }

    public function test_validate_after_correction_verifies_the_new_number(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $service = app(PhoneVerificationService::class);
        $service->sendForOnboarding($customer, '351922222222');

        $code = PhoneVerification::query()
            ->where('customer_id', $customer->id)
            ->where('phone', '351922222222')
            ->value('code');

        $this->assertTrue($service->verify($customer->fresh(), '351922222222', (string) $code));
        $this->assertSame(
            CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
            $customer->fresh()->status,
        );
    }

    public function test_send_via_whatsapp_does_not_call_sms(): void
    {
        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldNotReceive('send');
        });

        $this->mock(WhatsappGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->with('351911111111', \Mockery::on(fn (string $message) => str_contains($message, 'código')))
                ->andReturn(WhatsappSendResult::fromProvider(201, [
                    'key' => ['id' => 'WA1'],
                    'status' => 'PENDING',
                ]));
        });

        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $sent = app(PhoneVerificationService::class)->sendForOnboarding($customer, null, true);

        $this->assertTrue($sent);
        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'verified_at' => null,
        ]);
    }

    public function test_send_via_whatsapp_deletes_code_when_provider_fails(): void
    {
        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldNotReceive('send');
        });

        $this->mock(WhatsappGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->andReturn(WhatsappSendResult::failed('Erro de comunicação com o serviço de WhatsApp.'));
        });

        $customer = Customer::factory()->create([
            'phone' => '351911111111',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $sent = app(PhoneVerificationService::class)->sendForOnboarding($customer, null, true);

        $this->assertFalse($sent);
        $this->assertDatabaseMissing('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351911111111',
        ]);
    }
}
