<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->fail(
                'Tests refused to run on database connection "'
                .config('database.default')
                .'" (database: '
                .config('database.connections.'.config('database.default').'.database')
                .'). Use sqlite :memory: only.'
            );
        }
    }
}
