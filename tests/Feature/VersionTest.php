<?php

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\SemanticVersion;
use Infinity\Evolver\Version\TargetVersionResolverFactory;
use Infinity\Evolver\Version\VersionManager;

test('semantic version normalization', function () {
    $v1 = new SemanticVersion('v1.2.3');
    $v2 = new SemanticVersion('1.2.3');

    expect($v1->value())
        ->toBe('1.2.3')
        ->and($v2->value())
        ->toBe('1.2.3');
});

test('semantic version comparison', function () {
    $v1 = new SemanticVersion('1.0.0');
    $v2 = new SemanticVersion('1.1.0');
    $v3 = new SemanticVersion('1.1.0');

    expect($v2->isGreaterThanOrEqual($v1))->toBeTrue()
        ->and($v2->isGreaterThanOrEqual($v3))->toBeTrue()
        ->and($v1->isLessThan($v2))->toBeTrue()
        ->and($v2->compareTo($v1))->toBe(1)
        ->and($v1->compareTo($v2))->toBe(-1)
        ->and($v2->compareTo($v3))->toBe(0);
});

test('version manager resolves current version', function () {
    $repo = Mockery::mock(ActionRepository::class);
    $factory = new TargetVersionResolverFactory;

    $repo->shouldReceive('getCurrentVersion')
        ->andReturn('1.2.3');

    config(['evolver.versioning.format' => 'semver']);

    $manager = new VersionManager($repo, $factory);
    $current = $manager->current();

    expect($current)->toBeInstanceOf(SemanticVersion::class)
        ->and($current->value())->toBe('1.2.3');
});

test('version manager resolves target from config', function () {
    $repo = Mockery::mock(ActionRepository::class);
    $factory = new TargetVersionResolverFactory;

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '2.0.0',
        'evolver.versioning.format' => 'semver',
    ]);

    $manager = new VersionManager($repo, $factory);
    $target = $manager->targetRequired();

    expect($target->value())
        ->toBe('2.0.0');
});

test('version manager throws exception on unresolvable target', function () {
    $repo = Mockery::mock(ActionRepository::class);
    $factory = new TargetVersionResolverFactory;

    config([
        'evolver.versioning.target.resolver' => 'file',
        'evolver.versioning.target.file.path' => 'non-existent.txt',
        'evolver.versioning.target.required' => true,
    ]);

    $manager = new VersionManager($repo, $factory);

    expect(fn () => $manager->targetRequired())
        ->toThrow(VersionResolutionException::class);
});

test('version manager returns null on unresolvable target if not required', function () {
    $repo = Mockery::mock(ActionRepository::class);
    $factory = new TargetVersionResolverFactory;

    config([
        'evolver.versioning.target.resolver' => 'file',
        'evolver.versioning.target.file.path' => 'non-existent.txt',
        'evolver.versioning.target.required' => false,
    ]);

    $manager = new VersionManager($repo, $factory);

    expect($manager->targetRequired())->toBeNull();
});

test('target version resolver factory throws exception for unknown resolver', function () {
    config(['evolver.versioning.target.resolver' => 'unknown']);

    $factory = new TargetVersionResolverFactory;

    expect(fn () => $factory->make())
        ->toThrow(VersionResolutionException::class, 'Unknown version resolver: unknown');
});
