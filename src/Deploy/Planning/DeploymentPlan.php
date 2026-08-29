<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Version\SemanticVersion;

final class DeploymentPlan
{
    /**
     * Create a deployment plan.
     *
     * @param  list<ActionPlan>  $actions
     */
    public function __construct(
        public readonly array $actions,
        public readonly ?SemanticVersion $targetVersion,
    ) {}

    /**
     * Get the actions selected for execution in planned order.
     *
     * @return list<ActionPlan>
     */
    public function pending(): array
    {
        return collect($this->actions)
            ->filter(static fn (ActionPlan $action): bool => $action->status === ActionStatus::Pending)
            ->values()
            ->all();
    }

    /**
     * Create a plan containing only pending actions.
     */
    public function executable(): ExecutionPlan
    {
        return new ExecutionPlan($this->pending(), $this->targetVersion);
    }

    /**
     * Create a plan containing only the given action identities.
     *
     * @param  list<string>  $actionIds
     */
    public function only(array $actionIds): self
    {
        $identities = collect($actionIds)->flip();

        return new self(
            collect($this->actions)
                ->filter(static fn (ActionPlan $action): bool => $identities->has($action->descriptor->actionId))
                ->values()
                ->all(),
            $this->targetVersion,
        );
    }

    /**
     * Mark the given action identities as successfully executed.
     *
     * @param  list<string>  $actionIds
     */
    public function markExecuted(array $actionIds): self
    {
        $identities = collect($actionIds)->flip();

        return new self(
            collect($this->actions)
                ->map(static function (ActionPlan $action) use ($identities): ActionPlan {
                    if (! $identities->has($action->descriptor->actionId)) {
                        return $action;
                    }

                    return new ActionPlan(
                        $action->descriptor,
                        $action->action,
                        ActionStatus::Executed,
                        $action->introducedIn,
                        $action->requiredUntil,
                    );
                })
                ->all(),
            $this->targetVersion,
        );
    }
}
