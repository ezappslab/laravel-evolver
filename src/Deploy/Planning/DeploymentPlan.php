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

    /**
     * Create a plan containing only pending actions.
     */
    public function executable(): self
    {
        return new self($this->pending(), $this->targetVersion);
    }

    /**
     * Create a plan containing only the given action identities.
     *
     * @param  list<string>  $actionIds
     */
    public function only(array $actionIds): self
    {
        return new self(
            array_values(array_filter(
                $this->actions,
                static fn (ActionPlan $action): bool => in_array($action->descriptor->actionId, $actionIds, true),
            )),
            $this->targetVersion,
        );
    }
}
