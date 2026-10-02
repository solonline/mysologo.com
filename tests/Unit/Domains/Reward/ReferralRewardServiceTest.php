<?php

namespace Tests\Unit\Domains\Reward;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Events\CustomerApproved;
use App\Domains\Reward\Contracts\RewardPolicyInterface;
use App\Domains\Reward\Enums\RewardProgramSlug;
use App\Domains\Reward\Enums\RewardStatus;
use App\Domains\Reward\Listeners\RecordReferralOnCustomerApproved;
use App\Domains\Reward\Policies\ActiveCustomerReferralPolicy;
use App\Domains\Reward\Services\ReferralRewardService;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReferralRewardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_binds_reward_stack(): void
    {
        $this->assertInstanceOf(ActiveCustomerReferralPolicy::class, $this->app->make(RewardPolicyInterface::class));
        $this->assertInstanceOf(ReferralRewardService::class, $this->app->make(ReferralRewardService::class));
        $this->assertSame(RewardProgramSlug::Referral->value, RewardProgram::referral()->slug);
    }

    public function test_policy_marks_active_customer_eligible_without_amount(): void
    {
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);

        $decision = app(ActiveCustomerReferralPolicy::class)->evaluate($referred);

        $this->assertSame(RewardStatus::Eligible, $decision->status);
        $this->assertNull($decision->amountCents);
        $this->assertNull($decision->currency);
        $this->assertNotNull($decision->qualifiedAt);
    }

    public function test_policy_keeps_non_active_customer_pending(): void
    {
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);

        $decision = app(ActiveCustomerReferralPolicy::class)->evaluate($referred);

        $this->assertSame(RewardStatus::Pending, $decision->status);
        $this->assertNull($decision->qualifiedAt);
    }

    public function test_does_not_record_when_customer_has_no_referrer(): void
    {
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => null,
        ]);

        $reward = app(ReferralRewardService::class)->recordFromApprovedCustomer($referred);

        $this->assertNull($reward);
        $this->assertSame(0, Reward::query()->count());
    }

    public function test_records_eligible_reward_when_referred_customer_is_approved(): void
    {
        $referrer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);

        $reward = app(ReferralRewardService::class)->recordFromApprovedCustomer($referred);

        $this->assertNotNull($reward);
        $this->assertSame($referrer->id, $reward->customer_id);
        $this->assertSame(RewardProgram::referral()->id, $reward->program_id);
        $this->assertSame($referred->id, $reward->referred_id);
        $this->assertSame(RewardStatus::Eligible, $reward->status);
        $this->assertNull($reward->amount_cents);
        $this->assertNull($reward->paid_at);
        $this->assertNotNull($reward->qualified_at);
    }

    public function test_second_record_is_idempotent(): void
    {
        $referrer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);

        $service = app(ReferralRewardService::class);
        $first = $service->recordFromApprovedCustomer($referred);
        $second = $service->recordFromApprovedCustomer($referred);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Reward::query()->count());
    }

    public function test_listener_records_reward_on_customer_approved(): void
    {
        $referrer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $referred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);

        app(RecordReferralOnCustomerApproved::class)->handle(new CustomerApproved($referred));

        $this->assertDatabaseHas('rewards', [
            'customer_id' => $referrer->id,
            'program_id' => RewardProgram::referral()->id,
            'referred_id' => $referred->id,
            'status' => RewardStatus::Eligible->value,
        ]);
    }

    public function test_listener_is_registered_for_customer_approved(): void
    {
        $listeners = Event::getRawListeners()[CustomerApproved::class] ?? [];

        $this->assertTrue(collect($listeners)->contains(function ($listener) {
            if ($listener === RecordReferralOnCustomerApproved::class) {
                return true;
            }

            return is_array($listener) && ($listener[0] ?? null) === RecordReferralOnCustomerApproved::class;
        }));
    }

    public function test_backfill_creates_rewards_for_existing_referrals(): void
    {
        $referrer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $activeReferred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);
        $pendingReferred = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'referred_by_customer_id' => $referrer->id,
        ]);
        Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => null,
        ]);

        $service = app(ReferralRewardService::class);
        $first = $service->backfillFromExistingReferrals();
        $second = $service->backfillFromExistingReferrals();

        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['existing']);
        $this->assertSame(0, $first['ignored']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['existing']);

        $this->assertDatabaseHas('rewards', [
            'customer_id' => $referrer->id,
            'referred_id' => $activeReferred->id,
            'status' => RewardStatus::Eligible->value,
        ]);
        $this->assertDatabaseHas('rewards', [
            'customer_id' => $referrer->id,
            'referred_id' => $pendingReferred->id,
            'status' => RewardStatus::Pending->value,
        ]);
    }
}
