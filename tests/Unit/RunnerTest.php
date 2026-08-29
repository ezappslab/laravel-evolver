<?php

use Illuminate\Database\ConnectionInterface;
use Infinity\Evolver\Contracts\ActionIntegrityVerifier;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ExecutionPlan;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;

test('runner returns immediately when no actions are pending', function (): void {
    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldNotReceive('record');
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldNotReceive('transaction');
    $integrity = Mockery::mock(ActionIntegrityVerifier::class);
    $integrity->shouldNotReceive('verify');
    $runner = new Runner($repository, TransactionMode::EntireRun, $connection, $integrity);

    $result = $runner->run(new ExecutionPlan([], null), 'batch');

    expect($result->committedActionIds)->toBeEmpty();
});
