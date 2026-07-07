<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $this->forceTestingDatabaseEnvironment();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->forceTestingDatabaseConfiguration($app);

        return $app;
    }

    /**
     * @param  Application  $app
     */
    private function forceTestingDatabaseConfiguration($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);

        $app['db']->purge('sqlite');
        $app['db']->setDefaultConnection('sqlite');
    }

    /**
     * Never allow PHPUnit to touch the development MySQL database.
     * RefreshDatabase runs migrate:fresh and will wipe real data otherwise.
     */
    private function forceTestingDatabaseEnvironment(): void
    {
        foreach ([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
