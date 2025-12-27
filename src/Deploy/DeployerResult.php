<?php

namespace Infinity\Evolver\Deploy;

use Infinity\Evolver\Contracts\Version;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;

/**
 * Represents the result of a deployment operation.
 */
class DeployerResult
{
    /**
     * Create a new deployer result instance.
     *
     * @param  string  $batchId  The unique identifier for the deployment batch.
     * @param  Version  $targetVersion  The target version of the deployment.
     * @param  ActionDescriptor[]  $plannedToRun  The actions that were planned to be executed.
     * @param  array<int, array{descriptor: ActionDescriptor, reason: string}>  $skipped  The actions that were skipped.
     */
    public function __construct(
        public readonly string $batchId,
        public readonly Version $targetVersion,
        public readonly array $plannedToRun,
        public readonly array $skipped
    ) {}
}
