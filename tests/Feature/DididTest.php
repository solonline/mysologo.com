<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DididTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_session_via_api()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token')->plainTextToken;

        // Faz requisição autenticada
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/didit/session');

        $response->assertJsonStructure([
            'id',
            'user_id',
            'data',
            'created_at',
        ]);
    }
}
