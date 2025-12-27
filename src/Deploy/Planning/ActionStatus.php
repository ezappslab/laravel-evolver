<?php

namespace Infinity\Evolver\Deploy\Planning;

/**
 * Represents the status of an action in the deployment plan.
 */
enum ActionStatus: string
{
    case Pending = 'pending';
    case AlreadyRan = 'already_ran';
    case OutOfRange = 'out_of_range';
    case Changed = 'changed';
    case Success = 'success';
    case Failure = 'failure';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Get the color for the status in console output.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::AlreadyRan, self::Success => 'green',
            self::OutOfRange => 'gray',
            self::Changed, self::Failure => 'red',
        };
    }
}
