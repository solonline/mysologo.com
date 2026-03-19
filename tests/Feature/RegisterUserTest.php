<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterUserTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_register_user(): void
    {
        $data = [
            'name' => 'Bruno',
            'email' => 'testae@email.com',
            'username' => 'bruno123',
            'password' => 'password'
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

        $response->dump();
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
        $response->dump();
        $response->assertStatus(422);
    }

    public function test_login_user(): void
    {
        $user = \App\Models\User::factory()->create([
            'name' => 'Bruno',
            'email' => 'test@example.com',
            'username' => 'bruno123',
            'password' => Hash::make('password'),
        ]);

        $data = [
            'email' => 'test@example.com',
            'password' => 'password',
        ];

        $response = $this->post('/api/login', $data, [
            'Accept' => 'application/json',
        ]);

        $response->dump();
        $response->assertStatus(200);
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

        $response->dump();
        $response->assertStatus(401);
    }
}
