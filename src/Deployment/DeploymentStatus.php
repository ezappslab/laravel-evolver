<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Immutable classification of discovered files and persisted execution history. */
final readonly class DeploymentStatus
{
    /**
     * The state of every discovered and historical action keyed by identifier.
     *
     * @var array<string, ActionState>
     */
    private array $states;

    /**
     * Create a new deployment status instance.
     *
     * @param  list<ActionDescriptor>  $discovered
     * @param  list<ActionDescriptor>  $pending
     * @param  list<ActionDescriptor>  $changed
     * @param  list<ExecutionRecord>  $missing
     * @param  array<string, ExecutionRecord>  $executed
     */
    public function __construct(public array $discovered, public array $pending, public array $changed, public array $missing, public array $executed, public ?string $applicationVersion)
    {
        $states = [];
        foreach ($discovered as $action) {
            $states[$action->id] = isset($executed[$action->id]) ? ActionState::Executed : ActionState::NotApplicable;
        }
        foreach ($pending as $action) {
            $states[$action->id] = ActionState::Pending;
        }
        foreach ($changed as $action) {
            $states[$action->id] = ActionState::Changed;
        }
        foreach ($missing as $record) {
            $states[$record->id] = ActionState::Missing;
        }
        $this->states = $states;
    }

    /**
     * Get the state of the given action, or null when it is neither discovered nor executed.
     */
    public function stateOf(string $id): ?ActionState
    {
        return $this->states[$id] ?? null;
    }
}
