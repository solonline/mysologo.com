<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_user_service_me(): void
    {
        $userService = new \App\Services\UserService();
        $user = \App\Models\User::factory()->create();
        $this->assertEquals($user->id, $userService->me()->id);

    }
}
