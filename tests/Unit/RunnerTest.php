<?php

use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;

test('runner returns immediately when no actions are pending', function () {
    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldNotReceive('record');
    $runner = new Runner($repository, TransactionMode::EntireRun);

    $result = $runner->run(new DeploymentPlan([], null), 'batch');

    expect($result->committedActionIds)->toBe([]);
});
