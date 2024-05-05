<?php

namespace Infinity\Evolver\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Infinity\Evolver\LaravelEvolverServiceProvider;
use Infinity\Evolver\Tests\Models\User;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelEvolverServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Overriding default User model for tests
        $app['config']->set('evolver.user_model', User::class);
    }
}
