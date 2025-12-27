<?php

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\Version;
use Infinity\Evolver\Deploy\Deployer;
use Infinity\Evolver\Deploy\DeployerResult;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionPlanBuilder;
use Infinity\Evolver\Deploy\Running\ActionRunner;
use Infinity\Evolver\Version\VersionManager;

test('deployer can plan', function () {
    $planBuilder = Mockery::mock(ActionPlanBuilder::class);
    $runner = Mockery::mock(ActionRunner::class);
    $repository = Mockery::mock(ActionRepository::class);
    $versionManager = Mockery::mock(VersionManager::class);

    $plan = new ActionPlan([], []);
    $planBuilder->shouldReceive('build')->once()->andReturn($plan);

    $deployer = new Deployer($planBuilder, $runner, $repository, $versionManager);

    expect($deployer->plan())->toBe($plan);
});

test('deployer can deploy', function () {
    $planBuilder = Mockery::mock(ActionPlanBuilder::class);
    $runner = Mockery::mock(ActionRunner::class);
    $repository = Mockery::mock(ActionRepository::class);
    $versionManager = Mockery::mock(VersionManager::class);

    $plan = new ActionPlan([], []);
    $planBuilder->shouldReceive('build')->once()->andReturn($plan);

    $targetVersion = Mockery::mock(Version::class);
    $targetVersion->shouldReceive('value')->andReturn('2.0.0');
    $versionManager->shouldReceive('targetRequired')->once()->andReturn($targetVersion);

    $runner->shouldReceive('run')
        ->once()
        ->with($plan, Mockery::type('string'), $targetVersion, false);

    $repository->shouldReceive('setCurrentVersion')
        ->once()
        ->with('2.0.0');

    $deployer = new Deployer($planBuilder, $runner, $repository, $versionManager);
    $result = $deployer->deploy();

    expect($result)->toBeInstanceOf(DeployerResult::class)
        ->and($result->targetVersion)->toBe($targetVersion)
        ->and($result->plannedToRun)->toBe([])
        ->and($result->skipped)->toBe([])
        ->and($result->batchId)->toBeString();
});
