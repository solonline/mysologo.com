<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
    }

    /**
     * A basic feature test example.
     */
    public function test_register_user(): void
    {
        $data = [
            'name' => 'Example User',
            'username' => 'bruno123',
            'email' => 'testae@email.com',
            'phone' => '1234567890',
            'password' => 'password',
            'role' => 'retail_customer',
        ];
        $response = $this->post('/api/register', $data);
        $response->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'username',
                    'created_at',
                    'updated_at',
                ],
                'token',
            ],
        ]);

        // $response->dump();
        $response->assertStatus(201);
    }

    public function test_register_user_with_invalid_data(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'username' => '',
            'password' => 'short'
        ];
        $response = $this->post('/api/register', $data, [
            'Accept' => 'application/json',
        ]);
        // $response->dump();
        $response->assertStatus(422);
    }

    public function test_login_user(): void
    {

        $user = \App\Models\User::factory()->create();
        auth()->guard('api')->setUser($user);
        $user->assignRole(Role::findByName('retail_customer', 'api'));
        $data = [
            'email' => $user->email,
            'password' => 'password',
        ];
        $response = $this->post('/api/login', $data, [
            'Accept' => 'application/json',
        ]);

        // $response->dump();
        $response->assertStatus(200);
    }

    public function test_login_user_with_invalid_credentials(): void
    {
        $data = [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->post('/api/login', $data, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }
}
