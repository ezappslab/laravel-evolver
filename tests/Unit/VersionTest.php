<?php

use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\SemanticVersion;
use Infinity\Evolver\Version\VersionInterval;
use Infinity\Evolver\Version\VersionManager;
use Infinity\Evolver\Version\VersionStrategy;

test('none strategy resolves no version and disables filtering', function (): void {
    $manager = new VersionManager(VersionStrategy::None, null, true);

    expect($manager->target())->toBeNull()
        ->and($manager->filtersActions())->toBeFalse()
        ->and($manager->strategy())->toBe(VersionStrategy::None);
});

test('version manager uses exactly its selected strategy resolver', function (): void {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn('v2.3.4');

    $manager = new VersionManager(VersionStrategy::File, $resolver, true);

    $target = $manager->target();

    expect($target)->toBeInstanceOf(SemanticVersion::class)
        ->and($target?->value())->toBe('2.3.4');
});

test('required strategy reports an unresolved version', function (): void {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(null);

    $manager = new VersionManager(VersionStrategy::Config, $resolver, true);

    expect(fn () => $manager->target())
        ->toThrow(VersionResolutionException::class, 'strategy: config');
});

test('optional unresolved strategy produces no target but still filters', function (): void {
    $resolver = Mockery::mock(VersionResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(null);

    $manager = new VersionManager(VersionStrategy::Json, $resolver, false);

    expect($manager->target())->toBeNull()
        ->and($manager->filtersActions())->toBeTrue();
});

test('semantic versions support prerelease and build metadata', function (): void {
    $version = new SemanticVersion('v1.2.3-01alpha.1+build.42');

    expect($version->value())->toBe('1.2.3-01alpha.1+build.42')
        ->and($version->isLessThan(new SemanticVersion('1.2.3')))->toBeTrue();
});

test('invalid semantic versions are rejected at the version boundary', function (string $version): void {
    expect(fn () => new SemanticVersion($version))
        ->toThrow(VersionResolutionException::class, "Invalid semantic version: {$version}");
})->with([
    'missing patch' => '1.2',
    'leading zero major' => '01.2.3',
    'leading zero prerelease number' => '1.2.3-01',
    'empty prerelease' => '1.2.3-',
    'invalid character' => '1.2.3+build!',
]);

test('semantic version precedence follows the semver specification', function (string $lower, string $higher): void {
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

test('version intervals require the inclusive bound to precede the exclusive bound', function (string $introduced, string $until): void {
    expect(fn () => new VersionInterval(
        new SemanticVersion($introduced),
        new SemanticVersion($until),
    ))->toThrow(
        VersionResolutionException::class,
        "introducedIn [{$introduced}] must be less than requiredUntil [{$until}]",
    );
})->with([
    'equal bounds' => ['2.0.0', '2.0.0'],
    'reversed bounds' => ['2.0.0', '1.0.0'],
]);

test('version intervals are inclusive at introduction and exclusive at removal', function (): void {
    $interval = new VersionInterval(
        new SemanticVersion('1.2.0'),
        new SemanticVersion('2.0.0'),
    );

    expect($interval->contains(new SemanticVersion('1.2.0')))->toBeTrue()
        ->and($interval->contains(new SemanticVersion('1.9.9')))->toBeTrue()
        ->and($interval->contains(new SemanticVersion('2.0.0')))->toBeFalse();
});
