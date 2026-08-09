<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\Resolvers\GitTagResolver;
use Infinity\Evolver\Version\Resolvers\JsonFileResolver;
use Infinity\Evolver\Version\VersionManager;
use Infinity\Evolver\Version\VersionStrategy;

test('service provider converts scalar strategies and rejects invalid values', function (): void {
    config(['evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);

    expect($this->app->make(VersionManager::class)->strategy())->toBe(VersionStrategy::None);

    config(['evolver.versioning.strategy' => 'invalid']);
    $this->app->forgetInstance(VersionManager::class);

    expect(fn () => $this->app->make(VersionManager::class))
        ->toThrow(VersionResolutionException::class, 'Unknown version strategy: invalid');
});

test('git strategy resolves tags from the Laravel base path', function (): void {
    Process::fake(['git describe --tags --abbrev=0' => Process::result(output: "v2.3.4\n")]);

    expect((new GitTagResolver)->resolve())->toBe('2.3.4');

    Process::assertRan(fn (PendingProcess $process): bool => $process->path === base_path());
});

test('json strategy reports invalid json with file context', function (): void {
    $path = base_path('tests/invalid-version.json');
    File::put($path, '{invalid');

    expect(fn () => (new JsonFileResolver($path, 'version'))->resolve())
        ->toThrow(VersionResolutionException::class, "Invalid JSON in file: {$path}");
});

test('json strategy treats a file removed before reading as unresolved', function (): void {
    File::shouldReceive('exists')->once()->with('/versions.json')->andReturnTrue();
    File::shouldReceive('get')->once()->with('/versions.json')->andThrow(new FileNotFoundException);

    expect((new JsonFileResolver('/versions.json', 'version'))->resolve())->toBeNull();
});
