<?php

declare(strict_types=1);

use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\ActionState;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\EvolverManager;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Facades\Evolver;
use Infinity\Evolver\Testing\InMemoryActionSource;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\ActionLog;
use Tests\Support\RecordedAction;

it('deploys faked actions through the real commands and history', function (): void {
    $fake = Evolver::fake([
        '2026_01_01_000001_first' => $first = Mockery::mock(Action::class)->makePartial(),
        '2026_01_01_000002_second' => new class extends Action
        {
            public function up(): void {}
        },
    ]);
    $first->shouldReceive('up')->once();

    expect(commandLines('evolver:deploy'))->toContain('2026_01_01_000001_first executed', '2026_01_01_000002_second executed');

    $fake->assertExecuted('2026_01_01_000001_first', '2026_01_01_000002_second');
    $fake->touch('2026_01_01_000001_first');
    $fake->forget('2026_01_01_000002_second');

    $status = app(DeploymentManager::class)->status();
    expect($status->stateOf('2026_01_01_000001_first'))->toBe(ActionState::Changed)
        ->and($status->stateOf('2026_01_01_000002_second'))->toBe(ActionState::Missing);
    $this->artisan('evolver:deploy')->assertFailed();
});

it('rebuilds services that held the previous action source', function (): void {
    $before = app(DeploymentManager::class);
    $evolver = Evolver::getFacadeRoot();
    Evolver::fake(['001_first' => new RecordedAction('first')]);

    expect(app(ActionSource::class))->toBeInstanceOf(InMemoryActionSource::class)
        ->and(app(DeploymentManager::class))->not->toBe($before)
        ->and(Evolver::getFacadeRoot())->not->toBe($evolver)
        ->and(app(EvolverManager::class)->deployment)->toBe(app(DeploymentManager::class))
        ->and(app(DeploymentManager::class)->plan()->actions)->toHaveCount(1);
});

it('keeps the default version window and irreversible down on partial mocks', function (): void {
    $action = Mockery::mock(Action::class)->makePartial();
    $action->shouldReceive('up')->once();
    $fake = Evolver::fake(['001_partial' => $action]);
    config()->set('app.version', '1.0.0');
    config()->set('evolver.versioning.application.resolver', 'config');

    expect($action->introducedIn())->toBeNull()
        ->and($action->expiresAt())->toBeNull()
        ->and(app(DeploymentManager::class)->plan()->actions)->toHaveCount(1);

    app(DeploymentManager::class)->deploy();
    $this->artisan('evolver:rollback')->expectsOutputToContain('This Evolver action is irreversible.')->assertFailed();
    $fake->assertExecuted('001_partial');
});

it('asserts rollbacks and an empty history', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first'), '002_second' => new RecordedAction('second')]);
    $fake->assertNothingDeployed();

    app(DeploymentManager::class)->deploy();
    $fake->assertExecuted('001_first', '002_second');
    app(DeploymentManager::class)->rollback();

    $fake->assertRolledBack('001_first', '002_second')->assertNothingDeployed();
    expect(ActionLog::entries())->toBe(['first.up', 'second.up', 'second.down', 'first.down']);
});

it('fails assertions that the history does not support', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    app(DeploymentManager::class)->deploy();

    expect(fn () => $fake->assertExecuted('002_unknown'))->toThrow(ExpectationFailedException::class, 'Evolver action [002_unknown] was not executed.')
        ->and(fn () => $fake->assertNotExecuted('001_first'))->toThrow(ExpectationFailedException::class, 'Evolver action [001_first] was executed.')
        ->and(fn () => $fake->assertRolledBack('001_first'))->toThrow(ExpectationFailedException::class, 'Evolver action [001_first] was not rolled back.')
        ->and(fn () => $fake->assertRolledBack('002_unknown'))->toThrow(ExpectationFailedException::class, 'Evolver action [002_unknown] is not registered with the fake.')
        ->and(fn () => $fake->assertNothingDeployed())->toThrow(ExpectationFailedException::class, 'Evolver actions were deployed: 001_first.');
});

it('rejects duplicate identities and unknown touches', function (): void {
    expect(fn () => Evolver::fake(['001_a' => new RecordedAction('a'), '001_A' => new RecordedAction('A')]))->toThrow(InvalidActionException::class, 'Duplicate action identity [001_A].')
        ->and(fn () => Evolver::fake()->touch('001_unknown'))->toThrow(InvalidArgumentException::class, 'Action [001_unknown] is not registered.');
});
