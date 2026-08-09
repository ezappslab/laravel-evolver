<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Infinity\Evolver\Contracts\Action;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Database\DatabaseEvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Infinity\Evolver\Exceptions\EvolutionTableMissingException;
use Infinity\Evolver\Version\SemanticVersion;

function runnerPlan(?string $failure = null): DeploymentPlan
{
    $plans = [];

    foreach (['a', 'b', 'c'] as $id) {
        $action = new class($id, $failure === $id) extends Action
        {
            public function __construct(
                private readonly string $name,
                private readonly bool $fails,
            ) {}

            public function handle(): void
            {
                DB::table('evolver_effects')->insert(['name' => $this->name]);

                if ($this->fails) {
                    throw new RuntimeException("{$this->name} failed");
                }
            }
        };
        $plans[] = new ActionPlan(
            new ActionDescriptor($id, "/actions/{$id}.php", hash('sha256', $id)),
            $action,
            ActionStatus::Pending,
            null,
            null,
        );
    }

    return new DeploymentPlan($plans, new SemanticVersion('1.2.3'));
}

function runMode(TransactionMode $mode, ?string $failure = null): mixed
{
    $runner = new Runner(new DatabaseEvolutionRepository, $mode);

    return $runner->run(runnerPlan($failure), '00000000-0000-0000-0000-000000000001');
}

test('none keeps prior effects and records when a later action fails', function () {
    try {
        runMode(TransactionMode::None, 'c');
    } catch (ActionFailedException $exception) {
        expect($exception->actionId)->toBe('c')
            ->and($exception->getPrevious()?->getMessage())->toBe('c failed')
            ->and($exception->result->committedActionIds)->toBe(['a', 'b']);
    }

    expect(DB::table('evolver_effects')->pluck('name')->all())->toBe(['a', 'b', 'c'])
        ->and(DB::table('evolutions')->pluck('action_id')->all())->toBe(['a', 'b']);
});

test('per action rolls back the failed action and retains earlier commits', function () {
    try {
        runMode(TransactionMode::PerAction, 'c');
    } catch (ActionFailedException $exception) {
        expect($exception->result->committedActionIds)->toBe(['a', 'b']);
    }

    expect(DB::table('evolver_effects')->pluck('name')->all())->toBe(['a', 'b'])
        ->and(DB::table('evolutions')->pluck('action_id')->all())->toBe(['a', 'b']);
});

test('entire run failure rolls back all current run work and reports no commits', function () {
    try {
        runMode(TransactionMode::EntireRun, 'c');
    } catch (ActionFailedException $exception) {
        expect($exception->result->committedActionIds)->toBe([]);
    }

    expect(DB::table('evolver_effects')->count())->toBe(0)
        ->and(DB::table('evolutions')->count())->toBe(0);
});

test('entire run success commits action work and evolution records in order', function () {
    $result = runMode(TransactionMode::EntireRun);

    expect($result->committedActionIds)->toBe(['a', 'b', 'c'])
        ->and(DB::table('evolver_effects')->pluck('name')->all())->toBe(['a', 'b', 'c'])
        ->and(DB::table('evolutions')->pluck('action_id')->all())->toBe(['a', 'b', 'c']);
});

test('repository prevents duplicate committed identities', function () {
    $repository = new DatabaseEvolutionRepository;
    $repository->record('batch', 'a', hash('sha256', 'a'), null, 1);

    expect($repository->executed())->toHaveKey('a');
    expect(fn () => $repository->record('batch', 'a', hash('sha256', 'a'), null, 1))
        ->toThrow(QueryException::class);
});

test('runner returns immediately when no actions are pending', function () {
    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldNotReceive('record');
    $runner = new Runner($repository, TransactionMode::EntireRun);

    $result = $runner->run(new DeploymentPlan([], null), 'batch');

    expect($result->committedActionIds)->toBe([]);
});

test('repository reports a missing evolution table clearly', function () {
    Schema::drop('evolutions');

    expect(fn () => (new DatabaseEvolutionRepository)->executed())
        ->toThrow(EvolutionTableMissingException::class, 'Run your Laravel migrations');
});
