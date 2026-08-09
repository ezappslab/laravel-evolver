<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Infinity\Evolver\Deploy\Running\ExecutionResult;
use Throwable;

final class ActionFailedException extends EvolverException
{
    /**
     * Create an exception containing the failed action and committed result.
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        public readonly ExecutionResult $result,
        Throwable $previous,
    ) {
        parent::__construct("Action [{$actionId}] at [{$path}] failed: {$previous->getMessage()}", 0, $previous);
    }
}
