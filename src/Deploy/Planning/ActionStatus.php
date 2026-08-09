<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

enum ActionStatus: string
{
    /**
     * The action is applicable, unexecuted, and selected for deployment.
     */
    case Pending = 'pending';

    /**
     * The action was committed successfully in an earlier deployment.
     */
    case Executed = 'executed';

    /**
     * The action is unexecuted but outside the target-version interval.
     */
    case NotApplicable = 'not_applicable';
}
