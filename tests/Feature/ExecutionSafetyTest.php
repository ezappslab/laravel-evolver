<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Schema;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\Runner;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionExecutionException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\LockException;
use Infinity\Evolver\Facades\Evolver;
use Tests\Support\ActionLog;
use Tests\Support\RecordedAction;

it('uses the Laravel cache store without binding the lock provider contract', function (): void {
    $store = app('cache.store')->getStore();

    expect($store)->toBeInstanceOf(LockProvider::class)
        ->and(app('evolver.lock.provider'))->toBe($store)
        ->and(app()->bound(LockProvider::class))->toBeFalse();
});

it('reports committed actions and history per transaction mode', function (string $mode, array $completed): void {
    config()->set('evolver.transactions.mode', $mode);
    $failure = Mockery::mock(Action::class)->makePartial();
    $failure->shouldReceive('up')->once()->andThrow(new RuntimeException('failure'));
    $fake = Evolver::fake(['001_first' => new RecordedAction('first'), '002_failure' => $failure]);

    try {
        app(DeploymentManager::class)->deploy();
        $this->fail('The deployment should have failed.');
    } catch (ActionExecutionException $e) {
        expect($e->actionId)->toBe('002_failure')->and($e->completed)->toBe($completed);
    }

    expect(ActionLog::entries())->toBe(['first.up'])
        ->and(Schema::getConnection()->table('evolutions')->count())->toBe(count($completed));
    $fake->assertNotExecuted('002_failure');
})->with([
    'none' => ['none', ['001_first']],
    'action' => ['action', ['001_first']],
    'batch' => ['batch', []],
]);

it('rejects actions changed between planning and execution', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    $plan = app(DeploymentManager::class)->plan();
    $fake->touch('001_first');

    try {
        app(Runner::class)->run($plan);
        $this->fail('The run should have failed.');
    } catch (ActionExecutionException $e) {
        expect($e->getPrevious())->toBeInstanceOf(ActionChangedException::class)
            ->and($e->getMessage())->toContain('Action [001_first] changed after planning.');
    }

    expect(ActionLog::entries())->toBeEmpty();
    $fake->assertNothingDeployed();
});

it('rejects actions removed between planning and execution', function (): void {
    $fake = Evolver::fake(['001_first' => new RecordedAction('first')]);
    $plan = app(DeploymentManager::class)->plan();
    $fake->forget('001_first');

    try {
        app(Runner::class)->run($plan);
        $this->fail('The run should have failed.');
    } catch (ActionExecutionException $e) {
        expect($e->getPrevious())->toBeInstanceOf(InvalidActionException::class);
    }

    expect(ActionLog::entries())->toBeEmpty();
    $fake->assertNothingDeployed();
});

it('uses the configured lock duration', function (): void {
    $locks = new class implements LockProvider
    {
        public ?int $seconds = null;

        public function lock($name, $seconds = 0, $owner = null)
        {
            $this->seconds = $seconds;

            return (new ArrayStore)->lock($name, $seconds, $owner);
        }

        public function restoreLock($name, $owner)
        {
            return (new ArrayStore)->restoreLock($name, $owner);
        }
    };
    app()->instance('evolver.lock.provider', $locks);
    config()->set('evolver.lock.seconds', '900');
    app()->forgetInstance(DeploymentManager::class);
    app(DeploymentManager::class)->deploy();
    expect($locks->seconds)->toBe(900);
    foreach ([0, -5, 'abc'] as $invalid) {
        config()->set('evolver.lock.seconds', $invalid);
        app()->forgetInstance(DeploymentManager::class);
        expect(fn () => app(DeploymentManager::class))->toThrow(InvalidArgumentException::class, 'The Evolver lock seconds must be a positive integer.');
    }
});

it('rejects concurrent deployment execution', function (): void {
    $lock = app('evolver.lock.provider')->lock('evolver:deployment', 30);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(DeploymentManager::class)->deploy())->toThrow(LockException::class);
    } finally {
        $lock->release();
    }
});
