<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy;

use Illuminate\Support\Str;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Planning\Planner;
use Infinity\Evolver\Deploy\Running\Runner;

final class Deployer
{
    /**
     * Create a deployment coordinator.
     */
    public function __construct(
        private readonly Planner $planner,
        private readonly Runner $runner,
    ) {}

    /**
     * Build the deployment plan for the current application state.
     */
    public function plan(): DeploymentPlan
    {
        return $this->planner->plan();
    }

    /**
     * Plan and execute the pending evolution actions.
     */
    public function deploy(): DeployerResult
    {
        $execution = $this->runner->run($this->plan(), (string) Str::uuid());

        return new DeployerResult($this->plan(), $execution);
    }
}
