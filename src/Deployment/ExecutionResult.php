<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Reports actions committed before a run completed or failed. */
final readonly class ExecutionResult
{
    /**
     * Create a new execution result instance.
     *
     * The plan is kept so callers can describe the completed actions.
     *
     * @param  list<string>  $completed
     */
    public function __construct(public Direction $direction, public string $batch, public array $completed, public ExecutionPlan $plan) {}

    /**
     * Get the planned actions that completed, in execution order.
     *
     * @return list<PlannedAction>
     */
    public function completedActions(): array
    {
        $planned = collect($this->plan->actions)->keyBy(static fn (PlannedAction $action): string => $action->descriptor->id);

        return collect($this->completed)->map(static fn (string $id): PlannedAction => $planned[$id])->all();
    }
}
