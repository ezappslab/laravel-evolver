<?php

namespace Infinity\Evolver\Exceptions;

use Throwable;

class VersionResolutionException extends EvolverException
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Unable to resolve target version',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
