<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Models\Customer;
use App\Models\CustomerRetailDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerKycMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Storage::fake('local');
    }

    public function test_kyc_media_requires_admin_with_customer_permission(): void
    {
        [$customer, $document, $path] = $this->documentWithImage();

        $this->get(route('dashboard.customers.documents.media', [
            'customer' => $customer,
            'document' => $document,
            'field' => 'full_front_image',
        ]))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('dashboard.customers.documents.media', [
                'customer' => $customer,
                'document' => $document,
                'field' => 'full_front_image',
            ]))
            ->assertForbidden();

        $response = $this->actingAs($this->userWithPermission('customers.view'), 'web')
            ->get(route('dashboard.customers.documents.media', [
                'customer' => $customer,
                'document' => $document,
                'field' => 'full_front_image',
            ]));

        $response->assertOk();
        $response->assertHeader('cache-control', 'no-store, private');
        $this->assertSame('documento-privado', $response->streamedContent());

        Storage::disk('local')->assertExists($path);
    }

    public function test_kyc_media_is_not_served_for_another_customer(): void
    {
        [$customer, $document] = $this->documentWithImage();
        $other = Customer::factory()->create();

        $this->actingAs($this->userWithPermission('customers.view'), 'web')
            ->get(route('dashboard.customers.documents.media', [
                'customer' => $other,
                'document' => $document,
                'field' => 'full_front_image',
            ]))
            ->assertNotFound();

        $this->assertNotSame($other->id, $customer->id);
    }

    public function test_customer_show_links_kyc_images_to_the_private_route(): void
    {
        [$customer, $document] = $this->documentWithImage();
        $customer->update([
            'avatar' => "customer/{$customer->id}/didit/{$document->id}/portrait_image.jpg",
            'show_profile_pic' => true,
        ]);
        Storage::disk('local')->put(
            $customer->avatar,
            'retrato',
        );

        $mediaUrl = route('dashboard.customers.documents.media', [
            'customer' => $customer,
            'document' => $document,
            'field' => 'full_front_image',
        ], false);

        $this->actingAs($this->userWithPermission('customers.view'), 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee($mediaUrl, false)
            ->assertDontSee('/storage/customer/'.$customer->id.'/didit/', false);

        $this->assertNull($customer->fresh()->publicAvatarUrl());
    }

    /** @return array{0: Customer, 1: CustomerRetailDocument, 2: string} */
    private function documentWithImage(): array
    {
        $customer = Customer::factory()->create();
        $document = CustomerRetailDocument::query()->create([
            'customer_id' => $customer->id,
            'document_number' => 'didit:media-test',
        ]);
        $path = "customer/{$customer->id}/didit/{$document->id}/full_front_image.jpg";
        $document->update(['full_front_image' => $path]);
        Storage::disk('local')->put($path, 'documento-privado');

        return [$customer, $document->fresh(), $path];
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName($permission, 'web'));

        return $user;
    }
}
