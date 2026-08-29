<?php

use Illuminate\Database\ConnectionInterface;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;

test('runner returns immediately when no actions are pending', function (): void {
    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldNotReceive('record');
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldNotReceive('transaction');
    $runner = new Runner($repository, TransactionMode::EntireRun, $connection);

    $result = $runner->run(new DeploymentPlan([], null), 'batch');

    expect($result->committedActionIds)->toBeEmpty();
});
