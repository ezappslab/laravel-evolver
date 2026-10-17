<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Facades\Evolver;
use Tests\Support\ActionLog;
use Tests\Support\RecordedAction;

it('lists pending actions without executing them when pretending to deploy', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);

    $lines = commandLines('evolver:deploy', ['--pretend' => true]);

    expect($lines[0])->toBe('INFO Planning Evolver deployment.')
        ->and($lines[1])->toBe('001_first would execute')
        ->and($lines[2])->toMatch('/^⤷ batch [0-9a-f-]{36}$/');
    $fake->assertNothingDeployed();
});

it('shows the version window and batch of deployed actions', function (): void {
    config()->set('app.version', '2.0.0');
    config()->set('evolver.versioning.application.resolver', 'config');
    Evolver::fake(['001_first' => new RecordedAction('first', '1.0.0', '3.0.0'), '002_second' => new RecordedAction('second')]);

    $lines = commandLines('evolver:deploy');
    $batch = app(DeploymentManager::class)->status()->executed['001_first']->batch;

    expect($lines)->toBe([
        'INFO Deploying Evolver actions.',
        '001_first 1.0.0 → 3.0.0 executed',
        "⤷ batch {$batch}",
        '002_second executed',
        "⤷ batch {$batch}",
        'INFO Deployment complete.',
    ]);
});

it('reports when there is nothing to deploy or roll back', function (): void {
    Evolver::fake();

    expect(commandLines('evolver:deploy'))->toBe(['INFO Deploying Evolver actions.', 'INFO Nothing to deploy.'])
        ->and(commandLines('evolver:rollback'))->toBe(['INFO Rolling back the latest Evolver batch.', 'INFO Nothing to roll back.']);
});

it('rolls back the latest batch from the command line', function (): void {
    config()->set('app.version', '2.0.0');
    config()->set('evolver.versioning.application.resolver', 'config');
    $fake = Evolver::fake(['001_first' => new RecordedAction('first'), '002_second' => new RecordedAction('second', '1.0.0')]);
    $batch = app(DeploymentManager::class)->deploy()->batch;

    expect(commandLines('evolver:rollback', ['--pretend' => true]))->toBe([
        'INFO Planning rollback of the latest Evolver batch.',
        '002_second from 1.0.0 would roll back',
        "⤷ batch {$batch}",
        '001_first would roll back',
        "⤷ batch {$batch}",
        'INFO Rollback complete.',
    ]);
    $fake->assertExecuted('001_first', '002_second');

    expect(commandLines('evolver:rollback'))->toBe([
        'INFO Rolling back the latest Evolver batch.',
        '002_second from 1.0.0 rolled back',
        "⤷ batch {$batch}",
        '001_first rolled back',
        "⤷ batch {$batch}",
        'INFO Rollback complete.',
    ]);
    $fake->assertRolledBack('001_first', '002_second');
});

it('rolls back only the batch given on the command line', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    $first = app(DeploymentManager::class)->deploy();
    $fake->add('002_second', new RecordedAction('second'));
    app(DeploymentManager::class)->deploy();

    expect(commandLines('evolver:rollback', ['--batch' => $first->batch]))->toBe([
        "INFO Rolling back Evolver batch [{$first->batch}].",
        '001_first rolled back',
        "⤷ batch {$first->batch}",
        'INFO Rollback complete.',
    ]);
    $fake->assertRolledBack('001_first')->assertExecuted('002_second');
});

it('requires confirmation in production unless forced', function (string $command): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan($command)
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();
    $fake->assertNothingDeployed();

    $this->artisan($command, ['--force' => true])->assertSuccessful();
    expect(ActionLog::entries())->toBe($command === 'evolver:deploy' ? ['first.up'] : []);
})->with(['evolver:deploy', 'evolver:rollback']);
