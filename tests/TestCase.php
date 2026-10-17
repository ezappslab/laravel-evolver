<?php

namespace Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolver.actions.path', fixtures('missing'));
        config()->set('evolver.versioning.application.resolver', 'none');
    }
}
