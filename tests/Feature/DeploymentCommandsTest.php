<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Facades\Evolver;
use Tests\Support\ActionLog;
use Tests\Support\RecordedAction;

it('lists pending actions without executing them when pretending to deploy', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);

    $this->artisan('evolver:deploy', ['--pretend' => true])
        ->expectsOutput('Would execute: 001_first')
        ->assertSuccessful();

    $fake->assertNothingDeployed();
});

it('reports when there is nothing to deploy or roll back', function (): void {
    Evolver::fake();

    $this->artisan('evolver:deploy')->expectsOutputToContain('Nothing to deploy.')->assertSuccessful();
    $this->artisan('evolver:rollback')->expectsOutputToContain('Nothing to roll back.')->assertSuccessful();
});

it('rolls back the latest batch from the command line', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first'), '002_second' => new RecordedAction('second')]);
    app(DeploymentManager::class)->deploy();

    $this->artisan('evolver:rollback', ['--pretend' => true])
        ->expectsOutput('Would roll back: 002_second')
        ->expectsOutput('Would roll back: 001_first')
        ->assertSuccessful();
    $fake->assertExecuted('001_first', '002_second');

    $this->artisan('evolver:rollback')
        ->expectsOutput('Rolled back: 002_second')
        ->expectsOutput('Rolled back: 001_first')
        ->expectsOutputToContain('Rollback complete.')
        ->assertSuccessful();
    $fake->assertRolledBack('001_first', '002_second');
});

it('rolls back only the batch given on the command line', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    $first = app(DeploymentManager::class)->deploy();
    $fake->add('002_second', new RecordedAction('second'));
    app(DeploymentManager::class)->deploy();

    $this->artisan('evolver:rollback', ['--batch' => $first->batch])
        ->expectsOutput('Rolled back: 001_first')
        ->doesntExpectOutput('Rolled back: 002_second')
        ->assertSuccessful();

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
