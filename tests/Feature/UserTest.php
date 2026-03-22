<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\UserController;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_user_service_me(): void
    {
        $user = \App\Models\User::factory()->create([
            'name' => 'Bruno',
            'email' => 'test@example.com',
            'username' => 'bruno123',
            'password' => Hash::make('password'),
        ]);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');
        $response->assertOk();
        // $response->dump();
        $this->assertEquals($user->id, $response->json('data.id'));
    }
}
