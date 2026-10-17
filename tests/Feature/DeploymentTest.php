<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Database\DatabaseActionRepository;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\ActionDiscovery;
use Infinity\Evolver\Deployment\ActionState;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\Direction;
use Infinity\Evolver\Deployment\ExecutionPlan;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionExecutionException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\RepositoryUnavailableException;
use Infinity\Evolver\Facades\Evolver;
use Tests\Support\ActionLog;
use Tests\Support\RecordedAction;

it('deploys once in filename order and rolls back in reverse order', function (): void {
    useFixtures('deploy');
    $manager = app(DeploymentManager::class);

    $result = $manager->deploy();
    expect($result->direction)->toBe(Direction::Up)
        ->and($result->completed)->toBe(['2026_01_01_000001_first', '2026_01_01_000002_second'])
        ->and(ActionLog::entries())->toBe(['first.up', 'second.up'])
        ->and($manager->plan()->actions)->toBeEmpty();

    $manager->rollback();
    expect(ActionLog::entries())->toBe(['first.up', 'second.up', 'second.down', 'first.down'])
        ->and(Schema::getConnection()->table('evolutions')->count())->toBe(0);
});

it('rolls back an older batch from only its own records', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    $first = app(DeploymentManager::class)->deploy();
    $fake->add('002_second', new RecordedAction('second'));
    $second = app(DeploymentManager::class)->deploy();

    expect(array_keys(app(DatabaseActionRepository::class)->forBatch($first->batch)))->toBe(['001_first'])
        ->and(app(DatabaseActionRepository::class)->forBatch('unknown'))->toBeEmpty()
        ->and(app(DeploymentManager::class)->rollback($first->batch)->completed)->toBe(['001_first'])
        ->and(array_keys(app(DatabaseActionRepository::class)->all()))->toBe(['002_second'])
        ->and(app(DeploymentManager::class)->rollback($first->batch)->completed)->toBeEmpty()
        ->and($second->completed)->toBe(['002_second'])
        ->and(ActionLog::entries())->toBe(['first.up', 'second.up', 'first.down']);
});

it('rolls back without resolving the application version', function (): void {
    Evolver::fake(['001_first' => new RecordedAction('first')]);
    $manager = app(DeploymentManager::class);
    $manager->deploy();
    config()->set('evolver.versioning.application.resolver', 'file');
    config()->set('evolver.versioning.application.resolvers.file.path', fixtures('missing/VERSION'));

    expect($manager->rollback()->completed)->toBe(['001_first'])
        ->and(ActionLog::entries())->toBe(['first.up', 'first.down'])
        ->and($manager->rollbackPlan()->applicationVersion)->toBeNull();
});

it('uses the real plan for dry runs without side effects', function (): void {
    $action = Mockery::mock(Action::class)->makePartial();
    $action->shouldNotReceive('up');
    $fake = Evolver::fake(['001_test' => $action]);

    $plan = app(DeploymentManager::class)->deploy(true);

    expect($plan)->toBeInstanceOf(ExecutionPlan::class)->and($plan->actions)->toHaveCount(1);
    $fake->assertNothingDeployed();
});

it('applies optional version constraints', function (?string $version, array $expected): void {
    useFixtures('versioned');
    config()->set('app.version', $version);
    config()->set('evolver.versioning.application.resolver', 'config');

    expect(array_map(fn ($a) => $a->descriptor->id, app(DeploymentManager::class)->plan()->actions))->toBe($expected);
})->with([
    'no version' => [null, ['2026_01_01_000001_unversioned']],
    'inside every open window' => ['2.0.0', ['2026_01_01_000001_unversioned', '2026_01_01_000002_introduced', '2026_01_01_000005_window']],
    'before the introduced versions' => ['1.0.0', ['2026_01_01_000001_unversioned', '2026_01_01_000004_expired', '2026_01_01_000005_window']],
    'past every window' => ['3.0.0', ['2026_01_01_000001_unversioned', '2026_01_01_000002_introduced', '2026_01_01_000003_future']],
]);

it('detects changed and missing historical actions', function (): void {
    $path = useFixtures('deploy');
    recordHistory('2026_01_01_000001_first', hash_file('sha256', $path.'/2026_01_01_000001_first.php'));
    recordHistory('2026_01_01_000002_second', str_repeat('0', 64));
    expect(fn () => app(DeploymentManager::class)->plan())->toThrow(ActionChangedException::class, '2026_01_01_000002_second');

    Schema::getConnection()->table('evolutions')->where('action_id', '2026_01_01_000002_second')->delete();
    recordHistory('2026_01_01_000000_gone', str_repeat('0', 64));
    expect(fn () => app(DeploymentManager::class)->plan())->toThrow(InvalidActionException::class, 'Executed action files are missing: 2026_01_01_000000_gone.');
});

it('classifies every action state for status reporting', function (): void {
    $path = useFixtures('versioned');
    config()->set('app.version', '2.0.0');
    config()->set('evolver.versioning.application.resolver', 'config');
    $batch = '00000000-0000-0000-0000-000000000001';
    recordHistory('2026_01_01_000000_gone', str_repeat('0', 64), $batch);
    recordHistory('2026_01_01_000001_unversioned', hash_file('sha256', $path.'/2026_01_01_000001_unversioned.php'), $batch);
    recordHistory('2026_01_01_000002_introduced', str_repeat('0', 64), $batch);

    $status = app(DeploymentManager::class)->status();
    expect(array_map(fn (string $id) => $status->stateOf($id), [
        '2026_01_01_000000_gone',
        '2026_01_01_000001_unversioned',
        '2026_01_01_000002_introduced',
        '2026_01_01_000003_future',
        '2026_01_01_000004_expired',
        '2026_01_01_000005_window',
        'unknown',
    ]))->toBe([ActionState::Missing, ActionState::Executed, ActionState::Changed, ActionState::NotApplicable, ActionState::NotApplicable, ActionState::Pending, null]);

    $this->artisan('evolver:status')
        ->expectsOutputToContain('Application version: 2.0.0')
        ->expectsTable(['Action', 'Status', 'Batch'], [
            ['2026_01_01_000001_unversioned', 'executed', $batch],
            ['2026_01_01_000002_introduced', 'changed', $batch],
            ['2026_01_01_000003_future', 'not applicable', '—'],
            ['2026_01_01_000004_expired', 'not applicable', '—'],
            ['2026_01_01_000005_window', 'pending', '—'],
            ['2026_01_01_000000_gone', 'missing', $batch],
        ])
        ->assertSuccessful();
});

it('loads only the action files a plan needs', function (): void {
    $path = fixtures('deploy');
    recordHistory('2026_01_01_000001_first', hash_file('sha256', $path.'/2026_01_01_000001_first.php'));
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldNotReceive('getRequire')->with($path.'/2026_01_01_000001_first.php');
    $files->shouldReceive('getRequire')->once()->with($path.'/2026_01_01_000002_second.php')->passthru();
    app()->instance(ActionSource::class, new ActionDiscovery($files, $path));

    expect(app(DeploymentManager::class)->deploy()->completed)->toBe(['2026_01_01_000002_second'])
        ->and(app(DeploymentManager::class)->status()->stateOf('2026_01_01_000001_first'))->toBe(ActionState::Executed)
        ->and(ActionLog::entries())->toBe(['second.up']);
});

it('loads executed actions only when rolling them back', function (): void {
    $path = useFixtures('invalid/throws');
    recordHistory('2026_01_01_000001_throws', hash_file('sha256', $path.'/2026_01_01_000001_throws.php'));

    expect(app(DeploymentManager::class)->status()->stateOf('2026_01_01_000001_throws'))->toBe(ActionState::Executed)
        ->and(app(DeploymentManager::class)->plan()->isEmpty())->toBeTrue()
        ->and(fn () => app(DeploymentManager::class)->rollbackPlan())->toThrow(InvalidActionException::class, 'Unable to load action [2026_01_01_000001_throws].');
});

it('rejects pending actions that cannot be loaded', function (string $scenario, string $message): void {
    useFixtures($scenario);
    expect(fn () => app(DeploymentManager::class)->status())->toThrow(InvalidActionException::class, $message)
        ->and(fn () => app(DeploymentManager::class)->plan())->toThrow(InvalidActionException::class, $message);
})->with([
    'throws while loading' => ['invalid/throws', 'Unable to load action [2026_01_01_000001_throws].'],
    'returns a non-action' => ['invalid/non-action', 'Action [2026_01_01_000001_non_action] must return an instance of'],
    'declares an invalid version' => ['invalid/bad-version', 'Action [2026_01_01_000001_bad_version] has invalid version [latest].'],
]);

it('refuses to roll back irreversible actions', function (): void {
    useFixtures('irreversible');
    app(DeploymentManager::class)->deploy();

    expect(fn () => app(DeploymentManager::class)->rollback())->toThrow(ActionExecutionException::class, 'This Evolver action is irreversible.')
        ->and(ActionLog::entries())->toBe(['irreversible.up'])
        ->and(Schema::getConnection()->table('evolutions')->count())->toBe(1);
});

it('never records failed operations', function (): void {
    $action = Mockery::mock(Action::class)->makePartial();
    $action->shouldReceive('up')->once()->andThrow(new RuntimeException('failure'));
    $fake = Evolver::fake(['001_test' => $action]);

    expect(fn () => app(DeploymentManager::class)->deploy())->toThrow(ActionExecutionException::class, 'failure');
    $fake->assertNothingDeployed();
});

it('caches only a confirmed history table', function (): void {
    $repository = new DatabaseActionRepository(Schema::getConnection());
    Schema::rename('evolutions', 'evolutions_tmp');
    expect(fn () => $repository->ensureAvailable())->toThrow(RepositoryUnavailableException::class);
    Schema::rename('evolutions_tmp', 'evolutions');
    $repository->ensureAvailable();
    Schema::drop('evolutions');
    expect(fn () => $repository->latestBatch())->toThrow(QueryException::class);
});
