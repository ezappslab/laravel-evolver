<?php

declare(strict_types=1);

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deployment\Planner;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Testing\InMemoryActionSource;
use Infinity\Evolver\Versioning\VersionManager;
use Tests\Support\RecordedAction;

function plannerFor(RecordedAction $action, ?string $version): Planner
{
    config()->set('app.version', $version);
    config()->set('evolver.versioning.application.resolver', 'config');
    $repository = Mockery::mock(ActionRepository::class);
    $repository->shouldReceive('all')->andReturn([]);

    return new Planner(new InMemoryActionSource(['001_action' => $action]), $repository, app(VersionManager::class), true);
}

it('applies actions only inside their version window', function (?string $introducedIn, ?string $expiresAt, ?string $version, bool $applies): void {
    $status = plannerFor(new RecordedAction('action', $introducedIn, $expiresAt), $version)->status();

    expect($status->pending)->toHaveCount($applies ? 1 : 0);
})->with([
    'no window, no version' => [null, null, null, true],
    'no window, any version' => [null, null, '1.0.0', true],
    'windowed, no version' => ['2.0.0', null, null, false],
    'expiring, no version' => [null, '2.0.0', null, false],
    'introduced bound is inclusive' => ['2.0.0', null, '2.0.0', true],
    'before introduced' => ['2.0.0', null, '1.9.9', false],
    'expires bound is exclusive' => [null, '2.0.0', '2.0.0', false],
    'before expiry' => [null, '2.0.0', '1.9.9', true],
    'inside a closed window' => ['1.0', '2.0', '1.5.0', true],
    'v prefixes are ignored' => ['v1.0', 'v2.0', 'v1.5', true],
    'shorter versions sort first' => ['2.0', '2.0.0', '2.0', true],
    'short versions compare numerically' => ['1.10', null, '1.9', false],
    'pre-releases precede releases' => ['2.0.0', null, '2.0.0-rc.1', false],
    'pre-release bounds' => ['2.0.0-rc.1', null, '2.0.0', true],
]);

it('rejects malformed version windows', function (?string $introducedIn, ?string $expiresAt, string $message): void {
    expect(fn () => plannerFor(new RecordedAction('action', $introducedIn, $expiresAt), '1.0.0')->status())
        ->toThrow(InvalidActionException::class, $message);
})->with([
    'invalid introduced' => ['latest', null, 'Action [001_action] has invalid version [latest].'],
    'invalid expiry' => [null, '1.x', 'Action [001_action] has invalid version [1.x].'],
    'uppercase prefix' => ['V1.0', null, 'Action [001_action] has invalid version [V1.0].'],
    'empty window' => ['2.0.0', 'v2.0.0', 'Action [001_action] has an empty version window.'],
    'inverted window' => ['3.0', '2.0', 'Action [001_action] has an empty version window.'],
]);
