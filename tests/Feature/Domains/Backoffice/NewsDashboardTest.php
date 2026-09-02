<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NewsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('news.view', 'web');
        Permission::findOrCreate('news.create', 'web');
        Permission::findOrCreate('news.edit', 'web');
        Permission::findOrCreate('news.delete', 'web');
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.news'))
            ->assertForbidden();
    }

    public function test_index_lists_news_with_permission(): void
    {
        $user = $this->userWithPermission('news.view');
        News::factory()->create(['title' => 'Aviso de manutenção']);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.news'))
            ->assertOk()
            ->assertSee('Notícias')
            ->assertSee('Aviso de manutenção');
    }

    public function test_create_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.news.create'))
            ->assertForbidden();
    }

    public function test_store_creates_published_news_and_sanitizes_html(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermission('news.create');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.news.store'), [
                'title' => 'Nova funcionalidade',
                'excerpt' => '',
                'body' => '<p>Olá <script>alert(1)</script><strong>mundo</strong></p><a href="javascript:alert(1)">x</a>',
                'status' => News::STATUS_PUBLISHED,
                'audience' => News::AUDIENCE_RETAIL,
                'pinned' => '1',
                'image' => UploadedFile::fake()->image('cover.jpg', 40, 40),
            ])
            ->assertRedirect(route('dashboard.news'));

        $news = News::query()->where('title', 'Nova funcionalidade')->first();
        $this->assertNotNull($news);
        $this->assertSame(News::STATUS_PUBLISHED, $news->status);
        $this->assertSame(News::AUDIENCE_RETAIL, $news->audience);
        $this->assertTrue($news->pinned);
        $this->assertSame($user->id, $news->created_by);
        $this->assertNotNull($news->published_at);
        $this->assertStringContainsString('<strong>mundo</strong>', $news->body);
        $this->assertStringNotContainsString('<script>', $news->body);
        $this->assertStringNotContainsString('javascript:', $news->body);
        $this->assertNotNull($news->image_path);
        Storage::disk('public')->assertExists($news->image_path);
    }

    public function test_update_and_delete_require_permission_and_work(): void
    {
        Storage::fake('public');
        $editor = $this->userWithPermission('news.edit');
        $deleter = $this->userWithPermission('news.delete');
        $news = News::factory()->create(['title' => 'Rascunho antigo']);

        $this->actingAs(User::factory()->create(), 'web')
            ->put(route('dashboard.news.update', $news), [
                'title' => 'Hack',
                'body' => '<p>nope</p>',
                'status' => News::STATUS_DRAFT,
                'audience' => News::AUDIENCE_ALL,
            ])
            ->assertForbidden();

        $this->actingAs($editor, 'web')
            ->put(route('dashboard.news.update', $news), [
                'title' => 'Rascunho actualizado',
                'body' => '<p>Conteúdo</p>',
                'status' => News::STATUS_DRAFT,
                'audience' => News::AUDIENCE_ALL,
            ])
            ->assertRedirect(route('dashboard.news'));

        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'title' => 'Rascunho actualizado',
        ]);

        $this->actingAs(User::factory()->create(), 'web')
            ->delete(route('dashboard.news.destroy', $news))
            ->assertForbidden();

        $this->actingAs($deleter, 'web')
            ->delete(route('dashboard.news.destroy', $news))
            ->assertRedirect(route('dashboard.news'));

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName($permission, 'web'));

        return $user;
    }
}
