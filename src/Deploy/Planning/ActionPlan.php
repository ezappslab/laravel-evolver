<?php

namespace Infinity\Evolver\Deploy\Planning;

/**
 * Represents the plan of actions to be executed or skipped.
 */
class ActionPlan
{
    /**
     * Create a new action plan instance.
     *
     * @param  ActionDescriptor[]  $toRun  An array of descriptors for actions to be executed.
     * @param  array<int, array{descriptor: ActionDescriptor, status: ActionStatus}>  $skipped  An array of skipped actions with their statuses.
     */
    public function __construct(
        public readonly array $toRun = [],
        public readonly array $skipped = []
    ) {}
}
