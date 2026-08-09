<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Throwable;

final class InvalidActionException extends EvolverException
{
    /**
     * Create an exception for an action file that cannot be materialized.
     */
    public function __construct(
        public readonly string $path,
        string $message = 'Invalid action file',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
