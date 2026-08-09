<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Contracts\Version;

final class DeploymentPlan
{
    /**
     * Create a deployment plan.
     *
     * @param  list<ActionPlan>  $actions
     */
    public function __construct(
        public readonly array $actions,
        public readonly ?Version $targetVersion,
    ) {}

    /**
     * Get the actions selected for execution in planned order.
     *
     * @return list<ActionPlan>
     */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->actions,
            static fn (ActionPlan $action): bool => $action->status === ActionStatus::Pending,
        ));
    }
}
