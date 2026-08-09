<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy;

use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\ExecutionResult;

final class DeployerResult
{
    /**
     * Create a deployment result with the post-execution plan.
     */
    public function __construct(
        public readonly DeploymentPlan $plan,
        public readonly ExecutionResult $execution,
    ) {}
}
