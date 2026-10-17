<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Factory;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Versioning\Resolvers\ConfigResolver;
use Infinity\Evolver\Versioning\Resolvers\FileResolver;
use Infinity\Evolver\Versioning\Resolvers\GitTagResolver;
use Infinity\Evolver\Versioning\Resolvers\JsonResolver;
use Infinity\Evolver\Versioning\Resolvers\NoneResolver;

function versionFile(?string $contents): Filesystem
{
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->once()->with('/app/VERSION')->andReturn($contents !== null);
    if ($contents !== null) {
        $files->shouldReceive('get')->once()->with('/app/VERSION')->andReturn($contents);
    }

    return $files;
}

function gitResolver(array $fakes, bool $isDirectory = true, array $prefixes = ['v', 'V']): array
{
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('isDirectory')->once()->with('/app')->andReturn($isDirectory);
    $processes = (new Factory)->preventStrayProcesses()->fake($fakes);

    return [new GitTagResolver($files, $processes, '/app', $prefixes), $processes];
}

it('never resolves a version with the none resolver', function (): void {
    expect((new NoneResolver)->resolve())->toBeNull();
});

it('reads trimmed versions from configuration', function (mixed $value, ?string $expected): void {
    expect((new ConfigResolver(new Repository(['app' => ['version' => $value]]), 'app.version'))->resolve())->toBe($expected);
})->with([
    [' 1.2.3 ', '1.2.3'],
    [2, '2'],
    [null, null],
    ['', null],
]);

it('rejects non-scalar configuration versions', function (): void {
    expect(fn () => (new ConfigResolver(new Repository(['app' => ['version' => ['1.0']]]), 'app.version'))->resolve())
        ->toThrow(VersionResolutionException::class, 'Configuration [app.version] must contain a version string.');
});

it('reads trimmed versions from a file', function (string $contents, ?string $expected): void {
    expect((new FileResolver(versionFile($contents), '/app/VERSION'))->resolve())->toBe($expected);
})->with([
    [" 1.2.3\n", '1.2.3'],
    ["\n", null],
]);

it('rejects a missing version file', function (): void {
    expect(fn () => (new FileResolver(versionFile(null), '/app/VERSION'))->resolve())
        ->toThrow(VersionResolutionException::class, 'Configured version file is missing.');
});

it('reads dotted keys from JSON', function (string $json, string $key, string $expected): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->once()->with('/app/composer.json')->andReturnTrue();
    $files->shouldReceive('get')->once()->with('/app/composer.json')->andReturn($json);

    expect((new JsonResolver($files, '/app/composer.json', $key))->resolve())->toBe($expected);
})->with([
    ['{"version":" 1.0.0 "}', 'version', '1.0.0'],
    ['{"release":{"version":"2.0.0"}}', 'release.version', '2.0.0'],
    ['{"version":3}', 'version', '3'],
]);

it('rejects missing, malformed, and incomplete JSON', function (bool $exists, string $json, string $message): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->once()->with('/app/composer.json')->andReturn($exists);
    $files->shouldReceive('get')->with('/app/composer.json')->andReturn($json);

    expect(fn () => (new JsonResolver($files, '/app/composer.json', 'version'))->resolve())->toThrow(VersionResolutionException::class, $message);
})->with([
    'missing file' => [false, '', 'Configured JSON version file is missing.'],
    'malformed' => [true, '{"version":', 'Configured JSON version file is malformed.'],
    'missing key' => [true, '{"name":"app"}', 'JSON version key [version] is missing or invalid.'],
    'non-scalar key' => [true, '{"version":["1.0"]}', 'JSON version key [version] is missing or invalid.'],
]);

it('resolves the latest reachable Git tag', function (string $tag, ?string $expected, array $prefixes): void {
    [$resolver, $processes] = gitResolver([
        '*rev-parse*' => (new Factory)->result("true\n"),
        '*describe*' => (new Factory)->result($tag."\n"),
    ], prefixes: $prefixes);

    expect($resolver->resolve())->toBe($expected);
    $processes->assertRan(fn ($process) => $process->command === ['git', 'rev-parse', '--is-inside-work-tree'] && $process->path === '/app');
    $processes->assertRan(fn ($process) => $process->command === ['git', 'describe', '--tags', '--abbrev=0'] && $process->path === '/app');
})->with([
    'v prefix' => ['v1.2.0', '1.2.0', ['v', 'V']],
    'no prefix' => ['1.2.0', '1.2.0', ['v', 'V']],
    'only the first matching prefix' => ['vV1.2.0', 'V1.2.0', ['v', 'V']],
    'custom prefix' => ['release-2.0', '2.0', ['release-']],
    'empty prefixes are skipped' => ['v3.0', '3.0', ['', 'v']],
    'prefix only' => ['v', null, ['v', 'V']],
]);

it('requires an existing directory before running Git', function (): void {
    [$resolver, $processes] = gitResolver([], isDirectory: false);

    expect(fn () => $resolver->resolve())->toThrow(VersionResolutionException::class, 'Git metadata is unavailable.');
    $processes->assertNothingRan();
});

it('requires a Git work tree', function (string $output, int $exitCode): void {
    [$resolver, $processes] = gitResolver(['*rev-parse*' => (new Factory)->result($output, exitCode: $exitCode)]);

    expect(fn () => $resolver->resolve())->toThrow(VersionResolutionException::class, 'Git metadata is unavailable.');
    $processes->assertDidntRun(fn ($process) => $process->command === ['git', 'describe', '--tags', '--abbrev=0']);
})->with([
    'not a repository' => ['', 128],
    'inside the Git directory' => ["false\n", 0],
]);

it('requires a reachable Git tag', function (): void {
    [$resolver] = gitResolver([
        '*rev-parse*' => (new Factory)->result("true\n"),
        '*describe*' => (new Factory)->result(exitCode: 128),
    ]);

    expect(fn () => $resolver->resolve())->toThrow(VersionResolutionException::class, 'No reachable Git tag could be resolved.');
});
