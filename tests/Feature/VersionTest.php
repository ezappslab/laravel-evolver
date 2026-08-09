<?php

use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\SemanticVersion;
use Infinity\Evolver\Version\VersionManager;
use Infinity\Evolver\Version\VersionStrategy;

test('none strategy resolves no version and disables filtering', function () {
    $manager = new VersionManager(VersionStrategy::None, null, true);

    expect($manager->target())->toBeNull()
        ->and($manager->filtersActions())->toBeFalse()
        ->and($manager->strategy())->toBe(VersionStrategy::None);
});

test('version manager uses exactly its selected strategy resolver', function () {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('v2.3.4');

    $manager = new VersionManager(VersionStrategy::File, $resolver, true);

    $target = $manager->target();

    expect($target)->toBeInstanceOf(SemanticVersion::class)
        ->and($target?->value())->toBe('2.3.4');
});

test('required strategy reports an unresolved version', function () {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(null);

    $manager = new VersionManager(VersionStrategy::Config, $resolver, true);

    expect(fn () => $manager->target())
        ->toThrow(VersionResolutionException::class, 'strategy: config');
});

test('optional unresolved strategy produces no target but still filters', function () {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(null);

    $manager = new VersionManager(VersionStrategy::Json, $resolver, false);

    expect($manager->target())->toBeNull()
        ->and($manager->filtersActions())->toBeTrue();
});

test('service provider converts scalar strategies and rejects invalid values', function () {
    config(['evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);

    expect($this->app->make(VersionManager::class)->strategy())->toBe(VersionStrategy::None);

    config(['evolver.versioning.strategy' => 'invalid']);
    $this->app->forgetInstance(VersionManager::class);

    expect(fn () => $this->app->make(VersionManager::class))
        ->toThrow(VersionResolutionException::class, 'Unknown version strategy: invalid');
});

test('semantic versions support prerelease and build metadata', function () {
    $version = new SemanticVersion('v1.2.3-01alpha.1+build.42');

    expect($version->value())->toBe('1.2.3-01alpha.1+build.42')
        ->and($version->isLessThan(new SemanticVersion('1.2.3')))->toBeTrue();
});

test('invalid semantic versions are rejected at the version boundary', function (string $version) {
    expect(fn () => new SemanticVersion($version))
        ->toThrow(VersionResolutionException::class, "Invalid semantic version: {$version}");
})->with([
    'missing patch' => '1.2',
    'leading zero major' => '01.2.3',
    'leading zero prerelease number' => '1.2.3-01',
    'empty prerelease' => '1.2.3-',
    'invalid character' => '1.2.3+build!',
]);

test('semantic version precedence follows the semver specification', function (string $lower, string $higher) {
    expect((new SemanticVersion($lower))->isLessThan(new SemanticVersion($higher)))->toBeTrue();
})->with([
    ['1.0.0-alpha', '1.0.0-alpha.1'],
    ['1.0.0-alpha.1', '1.0.0-alpha.beta'],
    ['1.0.0-alpha.beta', '1.0.0-beta'],
    ['1.0.0-beta', '1.0.0-beta.2'],
    ['1.0.0-beta.2', '1.0.0-beta.11'],
    ['1.0.0-beta.11', '1.0.0-rc.1'],
    ['1.0.0-rc.1', '1.0.0'],
]);
