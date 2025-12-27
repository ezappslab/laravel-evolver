<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Running\ActionRunner;
use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Infinity\Evolver\Version\SemanticVersion;

test('action runner records success', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    $actionFile = $path.'/success_action.php';
    File::put($actionFile, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    $repository = Mockery::mock(ActionRepository::class);
    $materializer = new ActionMaterializer;
    $runner = new ActionRunner($repository, $materializer, TransactionMode::None);

    $descriptor = new ActionDescriptor('success_action', realpath($actionFile), md5_file($actionFile));
    $plan = new ActionPlan([$descriptor]);

    $repository->shouldReceive('getSuccessfulRunChecksum')->with('success_action')->andReturn(null);
    $repository->shouldReceive('recordSuccess')->once();

    $runner->run($plan, 'batch-1', new SemanticVersion('1.0.0'));

    File::deleteDirectory($path);
});

test('action runner records failure and throws exception', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    $actionFile = $path.'/fail_action.php';
    File::put($actionFile, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void { throw new \Exception("Failed!"); } 
    };');

    $repository = Mockery::mock(ActionRepository::class);
    $materializer = new ActionMaterializer;
    $runner = new ActionRunner($repository, $materializer, TransactionMode::None);

    $descriptor = new ActionDescriptor('fail_action', realpath($actionFile), md5_file($actionFile));
    $plan = new ActionPlan([$descriptor]);

    $repository->shouldReceive('getSuccessfulRunChecksum')->with('fail_action')->andReturn(null);
    $repository->shouldReceive('recordFailure')->once();

    expect(fn () => $runner->run($plan, 'batch-1', new SemanticVersion('1.0.0')))
        ->toThrow(ActionFailedException::class);

    File::deleteDirectory($path);
});

test('action runner respects checksum safety', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    $actionFile = $path.'/changed_action.php';
    File::put($actionFile, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    $repository = Mockery::mock(ActionRepository::class);
    $materializer = new ActionMaterializer;
    $runner = new ActionRunner($repository, $materializer, TransactionMode::None);

    $descriptor = new ActionDescriptor('changed_action', realpath($actionFile), md5_file($actionFile));
    $plan = new ActionPlan([$descriptor]);

    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('changed_action')
        ->andReturn('different-checksum');

    expect(fn () => $runner->run($plan, 'batch-1', new SemanticVersion('1.0.0')))
        ->toThrow(ActionChangedException::class);

    // Should NOT throw if allowChanged is true
    $repository->shouldReceive('recordSuccess')->once();
    $runner->run($plan, 'batch-1', new SemanticVersion('1.0.0'), allowChanged: true);

    File::deleteDirectory($path);
});

test('action runner fromConfig uses config value', function () {
    config(['evolver.transactions.mode' => TransactionMode::All]);

    $repository = Mockery::mock(ActionRepository::class);
    $materializer = new ActionMaterializer;

    $runner = ActionRunner::fromConfig($repository, $materializer);

    // Use reflection to check the protected property
    $reflection = new ReflectionClass($runner);
    $property = $reflection->getProperty('transactionMode');
    $property->setAccessible(true);

    expect($property->getValue($runner))->toBe(TransactionMode::All);
});
