<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Throwable;

class VersionResolutionException extends EvolverException
{
    /**
     * Create an exception for an invalid or unresolved version strategy.
     */
    public function __construct(
        string $message = 'Unable to resolve target version',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
