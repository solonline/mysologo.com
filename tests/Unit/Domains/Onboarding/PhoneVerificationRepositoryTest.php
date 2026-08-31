<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Repositories\PhoneVerificationRepository;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerificationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_resets_expiry_window_so_new_code_is_accepted(): void
    {
        $customer = Customer::factory()->create(['phone' => '351911111111']);
        $repository = app(PhoneVerificationRepository::class);

        $this->storeVerification($customer, '111111', issuedAt: now()->subHours(8));

        $repository->createOrUpdate($customer, '351911111111', '654321');

        $this->assertTrue($repository->verify($customer, '351911111111', '654321'));
    }

    public function test_code_issued_recently_is_valid_even_if_row_was_created_earlier(): void
    {
        $customer = Customer::factory()->create(['phone' => '351911111111']);

        $this->storeVerification($customer, '654321', issuedAt: now()->subMinutes(2), createdAt: now()->subHours(8));

        $this->assertTrue(app(PhoneVerificationRepository::class)->verify($customer, '351911111111', '654321'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $customer = Customer::factory()->create(['phone' => '351911111111']);

        $this->storeVerification(
            $customer,
            '654321',
            issuedAt: now()->subMinutes(PhoneVerificationRepository::CODE_TTL_MINUTES + 1),
        );

        $this->assertFalse(app(PhoneVerificationRepository::class)->verify($customer, '351911111111', '654321'));
    }

    public function test_verify_matches_phone_with_plus_prefix(): void
    {
        $customer = Customer::factory()->create(['phone' => '+351911111111']);
        $repository = app(PhoneVerificationRepository::class);

        $repository->createOrUpdate($customer, '+351911111111', '654321');

        $this->assertTrue($repository->verify($customer, '+351911111111', '654321'));
    }

    public function test_verify_trims_submitted_code(): void
    {
        $customer = Customer::factory()->create(['phone' => '351911111111']);
        $repository = app(PhoneVerificationRepository::class);

        $repository->createOrUpdate($customer, '351911111111', '654321');

        $this->assertTrue($repository->verify($customer, '351911111111', ' 654321 '));
    }

    private function storeVerification(
        Customer $customer,
        string $code,
        CarbonInterface $issuedAt,
        ?CarbonInterface $createdAt = null,
    ): PhoneVerification {
        $verification = new PhoneVerification;
        $verification->customer_id = $customer->id;
        $verification->phone = '351911111111';
        $verification->code = $code;
        $verification->verified_at = null;
        $verification->created_at = $createdAt ?? $issuedAt;
        $verification->updated_at = $issuedAt;
        $verification->timestamps = false;
        $verification->save();

        return $verification;
    }
}
