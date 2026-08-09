<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Running;

enum TransactionMode: string
{
    /**
     * Do not manage transactions around action execution.
     */
    case None = 'none';

    /**
     * Wrap each action and its Evolution record in a transaction.
     */
    case PerAction = 'per_action';

    /**
     * Wrap the entire deployment and all Evolution records in one transaction.
     */
    case EntireRun = 'entire_run';
}
