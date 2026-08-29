<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Exceptions\VersionResolverException;
use Infinity\Evolver\Version\Resolvers\ConfigKeyResolver;
use Infinity\Evolver\Version\Resolvers\GitTagResolver;
use Infinity\Evolver\Version\Resolvers\JsonFileResolver;
use Infinity\Evolver\Version\Resolvers\VersionFileResolver;
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
    Process::fake([
        'git tag --merged HEAD' => Process::result(output: "v2.3.4\n"),
        'git describe --tags --abbrev=0' => Process::result(output: "v2.3.4\n"),
    ]);

    expect((new GitTagResolver)->resolve())->toBe('2.3.4');

    Process::assertRan(fn (PendingProcess $process): bool => $process->path === base_path());
});

test('git strategy distinguishes no tags from a failed git command', function (): void {
    Process::fake(['git tag --merged HEAD' => Process::result(output: '')]);

    expect((new GitTagResolver)->resolve())->toBeNull();

    Process::fake(['git tag --merged HEAD' => Process::result(errorOutput: 'not a repository', exitCode: 128)]);

    expect(fn () => (new GitTagResolver)->resolve())
        ->toThrow(VersionResolverException::class, 'Unable to inspect Git tags: not a repository');
});

test('json strategy reports invalid json with file context', function (): void {
    $path = base_path('tests/invalid-version.json');
    File::put($path, '{invalid');

    expect(fn () => (new JsonFileResolver($path, 'version'))->resolve())
        ->toThrow(VersionResolverException::class, "Invalid JSON in file: {$path}");
});

test('json strategy treats a file removed before reading as unresolved', function (): void {
    File::shouldReceive('exists')->twice()->with('/versions.json')->andReturn(true, false);
    File::shouldReceive('get')->once()->with('/versions.json')->andThrow(new FileNotFoundException);

    expect((new JsonFileResolver('/versions.json', 'version'))->resolve())->toBeNull();
});

test('configured version sources distinguish absent values from invalid values', function (): void {
    config(['version-tests.absent' => null, 'version-tests.invalid' => 123]);

    expect((new ConfigKeyResolver('version-tests.absent'))->resolve())->toBeNull()
        ->and(fn () => (new ConfigKeyResolver('version-tests.invalid'))->resolve())
        ->toThrow(VersionResolverException::class, 'must contain a string');
});

test('version files distinguish disappearance from read failures', function (): void {
    File::shouldReceive('exists')->with('/version.txt')->andReturn(true, true);
    File::shouldReceive('get')->once()->with('/version.txt')->andThrow(new RuntimeException('permission denied'));

    expect(fn () => (new VersionFileResolver('/version.txt'))->resolve())
        ->toThrow(VersionResolverException::class, 'Unable to read version file: /version.txt');
});
