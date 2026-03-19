<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_user_model_exists()
    {
        $this->assertTrue(class_exists(\App\Models\User::class));
    }

    public function test_soma_dois_numeros()
    {
        $this->assertEquals(4, 2 + 2);
    }

}
