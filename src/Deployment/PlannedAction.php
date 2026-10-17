<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Couples discovered source with historical state required by rollback. */
final readonly class PlannedAction
{
    /**
     * Create a new planned action instance.
     */
    public function __construct(public ActionDescriptor $descriptor, public ?ExecutionRecord $record = null) {}
}
