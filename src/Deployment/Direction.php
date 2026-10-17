<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** The operation that a runner must invoke for every planned action. */
enum Direction: string
{
    case Up = 'up';
    case Down = 'down';
}
