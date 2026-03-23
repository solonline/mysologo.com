<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DididTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_didit_session_creation(): void
    {
        $user = User::factory()->create();
        auth()->guard('api')->setUser($user);
        $user->assignRole(Role::findByName('retail_customer', 'api'));

        $response = $this->post('/api/didit/session', [], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $user->createToken('api_token')->plainTextToken,
        ]);

        $response->dump();
        $response->assertStatus(201);
    }
}
