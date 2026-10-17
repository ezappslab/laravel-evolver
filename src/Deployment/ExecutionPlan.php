<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Immutable, side-effect-free instructions for Runner. */
final readonly class ExecutionPlan
{
    /**
     * Create a new execution plan instance.
     *
     * The actions are ordered for the selected direction. The application
     * version is only resolved for forward plans, where it is recorded.
     *
     * @param  list<PlannedAction>  $actions
     */
    public function __construct(public Direction $direction, public array $actions, public string $batch, public ?string $applicationVersion = null) {}

    /**
     * Determine whether the plan can be returned without invoking the runner.
     */
    public function isEmpty(): bool
    {
        return $this->actions === [];
    }
}
