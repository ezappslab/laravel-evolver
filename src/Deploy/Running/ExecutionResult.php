<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Running;

final class ExecutionResult
{
    /**
     * Create an execution result containing only committed actions.
     *
     * @param  list<string>  $committedActionIds
     */
    public function __construct(
        public readonly string $batchId,
        public readonly array $committedActionIds,
    ) {}
}
