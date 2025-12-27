<?php

namespace Infinity\Evolver\Exceptions;

use Throwable;

class ActionFailedException extends EvolverException
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        Throwable $previous
    ) {
        parent::__construct("Action [{$actionId}] at [{$path}] failed.", 0, $previous);
    }
}
