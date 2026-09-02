<?php

namespace Tests\Feature\Domains\Customer;

use App\Models\Customer;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_news(): void
    {
        $this->getJson('/api/customer/news')->assertUnauthorized();
    }

    public function test_list_hides_drafts_future_and_expired_items(): void
    {
        $customer = $this->retailCustomer();

        $visible = News::factory()->published()->create([
            'title' => 'Visível agora',
            'audience' => News::AUDIENCE_ALL,
        ]);
        News::factory()->draft()->create(['title' => 'Rascunho']);
        News::factory()->published()->create([
            'title' => 'Agendada',
            'published_at' => now()->addDay(),
        ]);
        News::factory()->published()->create([
            'title' => 'Expirada',
            'published_at' => now()->subDays(3),
            'expires_at' => now()->subHour(),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.title', 'Visível agora')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_retail_customer_does_not_see_corporate_news(): void
    {
        $retail = $this->retailCustomer();
        News::factory()->published()->forRetail()->create(['title' => 'Só retail']);
        $corporate = News::factory()->published()->forCorporate()->create(['title' => 'Só corporate']);

        $this->withToken($retail->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Só retail');

        $this->withToken($retail->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news/'.$corporate->id)
            ->assertNotFound();
    }

    public function test_corporate_and_responsible_see_corporate_audience(): void
    {
        $corporate = Customer::factory()->create(['type' => 'corporate']);
        $responsible = Customer::factory()->create(['type' => 'responsible']);
        News::factory()->published()->forCorporate()->create(['title' => 'Aviso empresas']);
        News::factory()->published()->forRetail()->create(['title' => 'Aviso particulares']);

        $this->withToken($corporate->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Aviso empresas');

        $this->withToken($responsible->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Aviso empresas');
    }

    public function test_show_returns_visible_item_and_platform_sees_all_audiences(): void
    {
        $platform = Customer::factory()->create(['type' => 'platform']);
        $item = News::factory()->published()->pinned()->forRetail()->create([
            'title' => 'Novidade retail',
            'body' => '<p>Detalhe</p>',
        ]);
        News::factory()->published()->forCorporate()->create(['title' => 'Novidade corporate']);

        $this->withToken($platform->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($platform->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news/'.$item->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Novidade retail')
            ->assertJsonPath('data.body', '<p>Detalhe</p>')
            ->assertJsonPath('data.pinned', true)
            ->assertJsonMissingPath('data.status');
    }

    public function test_pinned_items_come_first(): void
    {
        $customer = $this->retailCustomer();
        News::factory()->published()->create([
            'title' => 'Mais antiga',
            'published_at' => now()->subDays(2),
        ]);
        News::factory()->published()->pinned()->create([
            'title' => 'Fixada',
            'published_at' => now()->subDays(5),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->getJson('/api/customer/news')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Fixada')
            ->assertJsonPath('data.1.title', 'Mais antiga');
    }

    private function retailCustomer(): Customer
    {
        return Customer::factory()->create(['type' => 'retail']);
    }
}
