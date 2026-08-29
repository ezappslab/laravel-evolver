<?php

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Infinity\Evolver\Contracts\Action;
use Infinity\Evolver\Contracts\ActionIntegrityVerifier;
use Infinity\Evolver\Database\DatabaseEvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Infinity\Evolver\Exceptions\EvolutionTableMissingException;
use Infinity\Evolver\Models\Evolution;
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
    $connection = DB::connection();
    $integrity = Mockery::mock(ActionIntegrityVerifier::class);
    $integrity->shouldReceive('verify')->times(3);
    $runner = new Runner(new DatabaseEvolutionRepository($connection), $mode, $connection, $integrity);

    return $runner->run(runnerPlan($failure), '00000000-0000-0000-0000-000000000001');
}

test('none keeps prior effects and records when a later action fails', function (): void {
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

test('per action rolls back the failed action and retains earlier commits', function (): void {
    try {
        runMode(TransactionMode::PerAction, 'c');
    } catch (ActionFailedException $exception) {
        expect($exception->result->committedActionIds)->toBe(['a', 'b']);
    }

    expect(DB::table('evolver_effects')->pluck('name')->all())->toBe(['a', 'b'])
        ->and(DB::table('evolutions')->pluck('action_id')->all())->toBe(['a', 'b']);
});

test('entire run failure rolls back all current run work and reports no commits', function (): void {
    try {
        runMode(TransactionMode::EntireRun, 'c');
    } catch (ActionFailedException $exception) {
        expect($exception->result->committedActionIds)->toBeEmpty();
    }

    expect(DB::table('evolver_effects')->count())->toBe(0)
        ->and(DB::table('evolutions')->count())->toBe(0);
});

test('entire run success commits action work and evolution records in order', function (): void {
    $result = runMode(TransactionMode::EntireRun);

    expect($result->committedActionIds)->toBe(['a', 'b', 'c'])
        ->and(DB::table('evolver_effects')->pluck('name')->all())->toBe(['a', 'b', 'c'])
        ->and(DB::table('evolutions')->pluck('action_id')->all())->toBe(['a', 'b', 'c']);
});

test('repository prevents duplicate committed identities', function (): void {
    $repository = new DatabaseEvolutionRepository(DB::connection());
    $repository->record('batch', 'a', hash('sha256', 'a'), null, 1);

    expect($repository->executed())->toHaveKey('a')
        ->and((new Evolution)->usesTimestamps())->toBeFalse()
        ->and(fn () => $repository->record('batch', 'a', hash('sha256', 'a'), null, 1))->toThrow(QueryException::class);
});

test('repository reports a missing evolution table clearly', function (): void {
    Schema::drop('evolutions');

    expect(fn () => (new DatabaseEvolutionRepository(DB::connection()))->executed())
        ->toThrow(EvolutionTableMissingException::class, 'Run your Laravel migrations');
});

test('configured connection is shared by evolution persistence and transactions', function (): void {
    config([
        'database.connections.evolver_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'evolver.database.connection' => 'evolver_testing',
    ]);

    $connection = DB::connection('evolver_testing');
    $connection->getSchemaBuilder()->create('evolutions', function (Blueprint $table): void {
        $table->id();
        $table->uuid('batch_id');
        $table->string('action_id')->unique();
        $table->string('checksum', 64);
        $table->string('target_version')->nullable();
        $table->unsignedInteger('duration_ms');
        $table->timestamp('ran_at');
    });
    $connection->getSchemaBuilder()->create('evolver_effects', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $action = new class($connection) extends Action
    {
        public function __construct(
            private readonly Connection $connection,
        ) {}

        public function handle(): void
        {
            $this->connection->table('evolver_effects')->insert(['name' => 'rolled-back']);

            throw new RuntimeException('fail after write');
        }
    };
    $plan = new DeploymentPlan([
        new ActionPlan(
            new ActionDescriptor('dedicated', '/actions/dedicated.php', hash('sha256', 'dedicated')),
            $action,
            ActionStatus::Pending,
            null,
            null,
        ),
    ], null);

    $integrity = Mockery::mock(ActionIntegrityVerifier::class);
    $integrity->shouldReceive('verify')->once();
    $this->app->instance(ActionIntegrityVerifier::class, $integrity);
    $runner = $this->app->make(Runner::class);

    expect(fn () => $runner->run($plan, '00000000-0000-0000-0000-000000000001'))
        ->toThrow(ActionFailedException::class)
        ->and($connection->table('evolver_effects')->count())->toBe(0)
        ->and($connection->table('evolutions')->count())->toBe(0)
        ->and(DB::table('evolutions')->count())->toBe(0);
});
