<?php

namespace Infinity\Evolver\Exceptions;

class ActionChangedException extends EvolverException
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        public readonly string $expectedChecksum,
        public readonly string $actualChecksum
    ) {
        parent::__construct("Action [{$actionId}] at [{$path}] has changed. Expected checksum: [{$expectedChecksum}], actual: [{$actualChecksum}].");
    }
}
