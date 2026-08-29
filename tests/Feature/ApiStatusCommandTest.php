<?php

use Infinity\Evolver\Api\ApiVersionRegistry;

test('api status displays configured lifecycle states', function (): void {
    config(['evolver.api.versions' => [
        'v1' => [
            'deprecated_at' => '2000-01-01T00:00:00Z',
            'sunset_at' => '2999-01-01T00:00:00Z',
            'successor' => 'v2',
        ],
        'v2' => [],
    ]]);
    $this->app->forgetInstance(ApiVersionRegistry::class);

    $this->artisan('evolver:api-status')
        ->expectsOutputToContain('deprecated')
        ->expectsOutputToContain('active')
        ->assertSuccessful();
});
