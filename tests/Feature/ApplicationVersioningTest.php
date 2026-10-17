<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Facades\Evolver;
use Infinity\Evolver\Versioning\Resolvers\ConfigResolver;
use Infinity\Evolver\Versioning\Resolvers\FileResolver;
use Infinity\Evolver\Versioning\Resolvers\GitTagResolver;
use Infinity\Evolver\Versioning\Resolvers\JsonResolver;
use Infinity\Evolver\Versioning\Resolvers\NoneResolver;
use Infinity\Evolver\Versioning\VersionManager;

it('builds the configured application resolver', function (string $name, string $class): void {
    config()->set('evolver.versioning.application.resolver', $name);

    expect(app(VersionManager::class)->applicationResolver())->toBeInstanceOf($class);
})->with([
    ['none', NoneResolver::class],
    ['config', ConfigResolver::class],
    ['file', FileResolver::class],
    ['json', JsonResolver::class],
    ['git', GitTagResolver::class],
]);

it('rejects unknown application resolvers', function (): void {
    config()->set('evolver.versioning.application.resolver', 'nope');

    expect(fn () => app(VersionManager::class)->application())->toThrow(VersionResolutionException::class, 'Unknown application version resolver.');
});

it('resolves custom resolver classes from the container', function (): void {
    $custom = new class implements ApplicationVersionResolver
    {
        public function resolve(): ?string
        {
            return '9.8.7';
        }
    };
    app()->instance($custom::class, $custom);
    config()->set('evolver.versioning.application.resolver', $custom::class);

    expect(app(VersionManager::class)->application())->toBe('9.8.7');
});

it('resolves Git tags through the container process factory', function (): void {
    Process::preventStrayProcesses();
    Process::fake([
        '*rev-parse*' => Process::result('true'),
        '*describe*' => Process::result("v1.4.0\n"),
    ]);
    config()->set('evolver.versioning.application.resolver', 'git');

    expect(app(VersionManager::class)->application())->toBe('1.4.0');
    Process::assertRan(fn ($process) => $process->command === ['git', 'describe', '--tags', '--abbrev=0'] && $process->path === base_path());
});

it('rejects invalid resolved application versions', function (): void {
    config()->set('app.version', 'latest');
    config()->set('evolver.versioning.application.resolver', 'config');

    expect(fn () => app(VersionManager::class)->application())->toThrow(VersionResolutionException::class, 'The resolved application version is invalid.');
});

it('validates application versions with one shared format', function (string $version, bool $valid): void {
    expect(VersionManager::isValid($version))->toBe($valid);
})->with([
    ['1', true],
    ['1.2.3', true],
    ['v1.2.3.4', true],
    ['2.0.0-rc.1', true],
    ['2.0.0+build.5', true],
    ['', false],
    ['1.2.3.4.5', false],
    ['vv1.0', false],
    ['latest', false],
    ['1.0 ', false],
]);

it('rejects custom resolver classes without the resolver contract', function (): void {
    config()->set('evolver.versioning.application.resolver', stdClass::class);

    expect(fn () => app(VersionManager::class)->application())->toThrow(VersionResolutionException::class, 'The custom application resolver does not implement the required contract.');
});

it('exposes application and API versions through the facade', function (): void {
    config()->set('app.version', '1.2.0');
    config()->set('evolver.versioning.application.resolver', 'config');
    app()->instance('request', Request::create('/api/v4/users'));

    expect(Evolver::applicationVersion())->toBe('1.2.0')
        ->and(Evolver::apiVersion(Request::create('/api/v3/users')))->toBe('v3')
        ->and(Evolver::apiVersion())->toBe('v4');
});
