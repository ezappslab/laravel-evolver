<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Reports actions committed before a run completed or failed. */
final readonly class ExecutionResult
{
    /**
     * Create a new execution result instance.
     *
     * @param  list<string>  $completed
     */
    public function __construct(public Direction $direction, public string $batch, public array $completed) {}
}
