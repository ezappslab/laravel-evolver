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

    protected function getEnvironmentSetUp($app): void
    {
        // Overriding default User model for tests
        $app['config']->set('evolver.user_model', User::class);
    }
}
