<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Throwable;

final class VersionResolverException extends VersionResolutionException
{
    /**
     * Create an exception for a version source that could not be resolved reliably.
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
