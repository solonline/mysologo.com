<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Wise\Clients\WiseHttpClient;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithWiseSettings;
use Tests\TestCase;

class DashboardWiseBalancesTest extends TestCase
{
    use InteractsWithWiseSettings;
    use RefreshDatabase;

    public function test_dashboard_replaces_customers_card_with_wise_balances(): void
    {
        $this->seedWiseSettings();

        Http::fake([
            'https://api.wise-sandbox.com/'.WiseHttpClient::API_VERSION.'/profiles/12345/balances*' => Http::response([
                [
                    'id' => 65,
                    'currency' => 'USD',
                    'amount' => ['value' => 10, 'currency' => 'USD'],
                    'type' => 'STANDARD',
                ],
                [
                    'id' => 64,
                    'currency' => 'EUR',
                    'amount' => ['value' => 0.34, 'currency' => 'EUR'],
                    'type' => 'STANDARD',
                ],
                [
                    'id' => 66,
                    'currency' => 'GBP',
                    'amount' => ['value' => 0, 'currency' => 'GBP'],
                    'type' => 'STANDARD',
                ],
            ]),
        ]);

        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo Wise')
            ->assertSee('Soma de todos os saldos: clientes, subcontas e master.')
            ->assertSeeInOrder(['EUR', '0,34', 'USD', '10,00', 'GBP', '0,00'])
            ->assertSee('Suficiente')
            ->assertDontSee('Com SecurePay')
            ->assertDontSee('Docs pendentes');
    }

    public function test_dashboard_compares_wise_balance_with_the_sum_of_customer_allocations(): void
    {
        $this->seedWiseSettings();

        $first = Customer::factory()->create(['status' => CustomerLifecycleStatus::ACTIVE]);
        $second = Customer::factory()->create(['status' => CustomerLifecycleStatus::ACTIVE]);
        $platform = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'type' => 'platform',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $first->id,
            'currency' => 'EUR',
            'wallester_allocated' => 0,
            'pending' => 0,
            'wise_allocated' => 1_000,
        ]);
        CustomerBalance::query()->create([
            'customer_id' => $second->id,
            'currency' => 'EUR',
            'wallester_allocated' => 0,
            'pending' => 0,
            'wise_allocated' => 500,
        ]);
        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 0,
            'pending' => 0,
            'wise_allocated' => 2_500,
        ]);

        Http::fake([
            'https://api.wise-sandbox.com/'.WiseHttpClient::API_VERSION.'/profiles/12345/balances*' => Http::response([
                [
                    'id' => 64,
                    'currency' => 'EUR',
                    'amount' => ['value' => 0.34, 'currency' => 'EUR'],
                    'type' => 'STANDARD',
                ],
                [
                    'id' => 65,
                    'currency' => 'USD',
                    'amount' => ['value' => 10, 'currency' => 'USD'],
                    'type' => 'STANDARD',
                ],
            ]),
        ]);

        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['EUR', 'Insuficiente', 'Wise', '0,34', 'Clientes', '40,00'])
            ->assertDontSee('Plataforma')
            ->assertSeeInOrder(['USD', 'Suficiente', 'Wise', '10,00', 'Clientes', '0,00']);
    }

    public function test_dashboard_stays_available_when_wise_balances_fail(): void
    {
        $this->seedWiseSettings();

        Http::fake([
            'https://api.wise-sandbox.com/*' => Http::response(['error' => 'invalid_token'], 401),
        ]);

        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo Wise')
            ->assertSee('Não foi possível consultar a Wise.');
    }
}
