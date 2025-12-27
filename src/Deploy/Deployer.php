<?php

namespace Infinity\Evolver\Deploy;

use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionPlanBuilder;
use Infinity\Evolver\Deploy\Running\ActionRunner;
use Infinity\Evolver\Version\VersionManager;
use Throwable;

/**
 * Coordinates the planning and execution of actions.
 */
class Deployer
{
    /**
     * Create a new deployer instance.
     */
    public function __construct(
        protected ActionPlanBuilder $planBuilder,
        protected ActionRunner $runner,
        protected ActionRepository $repository,
        protected VersionManager $versionManager
    ) {}

    /**
     * Generate an action plan for the current state.
     */
    public function plan(): ActionPlan
    {
        return $this->planBuilder->build();
    }

    /**
     * Execute the deployment.
     *
     * @throws Throwable
     */
    public function deploy(bool $allowChanged = false): ?DeployerResult
    {
        $plan = $this->plan();
        $targetVersion = $this->versionManager->targetRequired();

        if ($targetVersion === null) {
            return null;
        }

        $batchId = (string) Str::uuid();

        $this->runner->run($plan, $batchId, $targetVersion, $allowChanged);

        $targetValue = $targetVersion->value();
        if ($targetValue !== null) {
            $this->repository->setCurrentVersion($targetValue);
        }

        return new DeployerResult(
            $batchId,
            $targetVersion,
            $plan->toRun,
            $plan->skipped
        );
    }
}
