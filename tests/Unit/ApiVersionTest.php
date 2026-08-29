<?php

use Infinity\Evolver\Api\ApiVersion;
use Infinity\Evolver\Api\ApiVersionLifecycle;
use Infinity\Evolver\Api\ApiVersionRegistryFactory;
use Infinity\Evolver\Api\ApiVersionState;
use Infinity\Evolver\Exceptions\InvalidApiVersionException;

test('api versions are normalized major identifiers', function (): void {
    expect((new ApiVersion('V2'))->value)->toBe('v2')
        ->and(fn () => new ApiVersion('2.1'))->toThrow(InvalidApiVersionException::class)
        ->and(fn () => new ApiVersion('v0'))->toThrow(InvalidApiVersionException::class);
});

test('api lifecycle transitions from active through deprecated to sunset', function (): void {
    $lifecycle = new ApiVersionLifecycle(
        new DateTimeImmutable('2027-01-01T00:00:00Z'),
        new DateTimeImmutable('2027-08-01T00:00:00Z'),
    );

    expect($lifecycle->stateAt(new DateTimeImmutable('2026-12-31T23:59:59Z')))->toBe(ApiVersionState::Active)
        ->and($lifecycle->stateAt(new DateTimeImmutable('2027-01-01T00:00:00Z')))->toBe(ApiVersionState::Deprecated)
        ->and($lifecycle->stateAt(new DateTimeImmutable('2027-08-01T00:00:00Z')))->toBe(ApiVersionState::Sunset);
});

test('api lifecycle rejects invalid date intervals', function (): void {
    expect(fn () => new ApiVersionLifecycle(null, new DateTimeImmutable('2027-08-01')))
        ->toThrow(InvalidArgumentException::class, 'requires a deprecation date')
        ->and(fn () => new ApiVersionLifecycle(
            new DateTimeImmutable('2027-08-01'),
            new DateTimeImmutable('2027-01-01'),
        ))->toThrow(InvalidArgumentException::class, 'must precede');
});

test('api registry validates successors and exposes configured definitions', function (): void {
    $factory = new ApiVersionRegistryFactory;
    $registry = $factory->fromArray([
        'v1' => ['successor' => 'v2', 'successor_url' => '/api/v2'],
        'v2' => [],
    ]);

    expect($registry->get(new ApiVersion('v1'))->successor?->value)->toBe('v2')
        ->and($registry->all())->toHaveCount(2)
        ->and(fn () => $factory->fromArray(['v1' => ['successor' => 'v2']]))
        ->toThrow(InvalidApiVersionException::class, 'is not registered');
});
