<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** The reported state of a discovered or historical action. */
enum ActionState: string
{
    case Pending = 'pending';
    case Executed = 'executed';
    case Changed = 'changed';
    case Missing = 'missing';
    case NotApplicable = 'not applicable';
}
