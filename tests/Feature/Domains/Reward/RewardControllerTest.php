<?php

namespace Tests\Feature\Domains\Reward;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Reward\Enums\RewardStatus;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_rewards(): void
    {
        $this->getJson('/api/reward/my-rewards')->assertUnauthorized();
    }

    public function test_customer_lists_own_rewards_with_program_and_referred(): void
    {
        $referrer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $referred = Customer::factory()->create([
            'name' => 'Indicado Teste',
            'username' => 'indicado_teste',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);
        $other = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
        ]);
        $program = RewardProgram::referral();

        Reward::query()->create([
            'customer_id' => $referrer->id,
            'program_id' => $program->id,
            'referred_id' => $referred->id,
            'status' => RewardStatus::Eligible,
            'qualified_at' => now(),
        ]);
        Reward::query()->create([
            'customer_id' => $other->id,
            'program_id' => $program->id,
            'referred_id' => Customer::factory()->create([
                'status' => CustomerLifecycleStatus::ACTIVE,
                'referred_by_customer_id' => $other->id,
            ])->id,
            'status' => RewardStatus::Eligible,
            'qualified_at' => now(),
        ]);

        $response = $this->withToken($referrer->createToken('test')->plainTextToken)
            ->getJson('/api/reward/my-rewards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', RewardStatus::Eligible->value)
            ->assertJsonPath('data.0.program.slug', 'referral')
            ->assertJsonPath('data.0.program.name', 'Indicação')
            ->assertJsonPath('data.0.program.valid_from', null)
            ->assertJsonPath('data.0.program.valid_until', null)
            ->assertJsonPath('data.0.referred.id', $referred->id)
            ->assertJsonPath('data.0.referred.username', 'indicado_teste')
            ->assertJsonMissingPath('data.0.referrer_id')
            ->assertJsonMissingPath('data.0.customer_id');

        $this->assertIsString($response->json('data.0.program.description'));
        $this->assertNotSame('', $response->json('data.0.program.description'));
    }
}
