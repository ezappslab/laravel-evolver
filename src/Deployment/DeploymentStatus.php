<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Immutable classification of discovered files and persisted execution history. */
final readonly class DeploymentStatus
{
    /**
     * Create a new deployment status instance.
     *
     * @param  list<ActionDescriptor>  $discovered
     * @param  list<ActionDescriptor>  $pending
     * @param  list<ActionDescriptor>  $changed
     * @param  list<ExecutionRecord>  $missing
     * @param  array<string, ExecutionRecord>  $executed
     */
    public function __construct(public array $discovered, public array $pending, public array $changed, public array $missing, public array $executed, public ?string $applicationVersion) {}
}
