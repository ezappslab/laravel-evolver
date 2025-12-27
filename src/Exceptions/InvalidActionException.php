<?php

namespace Infinity\Evolver\Exceptions;

use Throwable;

class InvalidActionException extends EvolverException
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct(
        public readonly string $path,
        string $message = 'Invalid action file',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
