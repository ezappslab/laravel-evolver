<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Version\SemanticVersion;
use InvalidArgumentException;

final class ExecutionPlan
{
    /** @var list<ActionPlan> */
    public readonly array $actions;

    /**
     * Create a plan containing only actions that are pending execution.
     *
     * @param  list<ActionPlan>  $actions
     */
    public function __construct(
        array $actions,
        public readonly ?SemanticVersion $targetVersion,
    ) {
        foreach ($actions as $action) {
            if ($action->status !== ActionStatus::Pending) {
                throw new InvalidArgumentException(
                    "Execution plan action [{$action->descriptor->actionId}] must be pending; "
                    ."[{$action->status->value}] given.",
                );
            }
        }

        $this->actions = $actions;
    }
}
