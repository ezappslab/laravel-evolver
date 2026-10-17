<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

/** Wraps an action failure while exposing actions committed earlier in the run. */
final class ActionExecutionException extends EvolverException
{
    /**
     * Create a new action execution exception instance.
     *
     * @param  list<string>  $completed
     */
    public function __construct(public readonly string $actionId, public readonly array $completed, \Throwable $previous)
    {
        parent::__construct("Evolver action [{$actionId}] failed: {$previous->getMessage()}", 0, $previous);
    }
}
