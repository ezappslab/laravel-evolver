<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

final class ActionChangedException extends EvolverException
{
    /**
     * Create an exception for a changed committed action file.
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        public readonly string $expectedChecksum,
        public readonly string $actualChecksum,
    ) {
        parent::__construct("Action [{$actionId}] at [{$path}] has changed. Expected checksum: [{$expectedChecksum}], actual: [{$actualChecksum}].");
    }
}
