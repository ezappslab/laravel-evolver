<?php

namespace Infinity\Evolver\Tests;

use Infinity\Evolver\LaravelEvolverServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelEvolverServiceProvider::class,
        ];
    }
}
