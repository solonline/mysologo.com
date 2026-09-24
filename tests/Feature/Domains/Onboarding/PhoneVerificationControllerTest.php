<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(['status' => 'success', 'body' => null, 'http_code' => 202]);
        });
    }

    public function test_send_without_body_uses_registered_phone(): void
    {
        $customer = $this->pendingPhoneCustomer('351911111111');

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/onboarding/phone/send')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('phone', '351911111111');
    }

    public function test_send_with_new_phone_corrects_unverified_number(): void
    {
        $customer = $this->pendingPhoneCustomer('351911111111');

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/onboarding/phone/send', ['phone' => '351922222222'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('phone', '351922222222');

        $this->assertSame('351922222222', $customer->fresh()->phone);
    }

    public function test_send_rejects_new_phone_when_current_is_already_verified(): void
    {
        $customer = $this->pendingPhoneCustomer('351911111111');
        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351911111111',
            'code' => '123456',
            'verified_at' => now(),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/onboarding/phone/send', ['phone' => '351922222222'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'phone_already_verified');

        $this->assertSame('351911111111', $customer->fresh()->phone);
    }

    public function test_send_with_whatsapp_true_uses_whatsapp_channel(): void
    {
        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldNotReceive('send');
        });

        $this->mock(WhatsappGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('sendText')
                ->once()
                ->with('351911111111', \Mockery::type('string'))
                ->andReturn(WhatsappSendResult::fromProvider(201, [
                    'key' => ['id' => 'WA1'],
                    'status' => 'PENDING',
                ]));
        });

        $customer = $this->pendingPhoneCustomer('351911111111');

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/onboarding/phone/send', ['whatsapp' => true])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('phone', '351911111111');
    }

    private function pendingPhoneCustomer(string $phone): Customer
    {
        return Customer::factory()->create([
            'phone' => $phone,
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
            'email_verified_at' => now(),
        ]);
    }
}
